<?php

declare(strict_types=1);

namespace App\Frontend\Presenters;

use App\Forms\RecipesForm;
use App\Repositories\ContentViewRepository;
use App\Repositories\RecipesRepository;
use App\Services\DashboardService;
use App\Services\LikeService;
use App\Services\RecipesService;
use App\Utils\PaginatorFactory;
use Nette\Application\UI\Form;
use Nette\DI\Attributes\Inject;
use Nette\Utils\ArrayHash;

final class RecipesPresenter extends BasePresenter
{
    #[Inject]
    public RecipesRepository $recipesRepository;

	#[Inject]
	public ContentViewRepository $contentViewRepository;

    #[Inject]
    public RecipesForm $recipesForm;

    #[Inject]
    public RecipesService $recipesService;

    #[Inject]
    public PaginatorFactory $paginatorFactory;

    #[Inject]
    public DashboardService $dashboardService;

    #[Inject]
    public LikeService $likeService;


    /** @var array<string, string> */
    private array $categoryMap = [
        'breakfasts' => 'snidane',
        'lunches'    => 'obedy',
        'snacks'     => 'svaciny',
        'dinners'    => 'vecere',
        'deserts'    => 'zdrave-mlsani',
    ];


    public function beforeRender(): void
    {
        parent::beforeRender();
        $this->template->activePage = $this->getAction();
    }


    public function renderCategory(string $category, int $page = 1): void
    {
        $page = max(1, $page);

        $viewerId = $this->getUserId();
        $authorId = $this->getActiveUserId() ?: $viewerId;

        $totalItems = $this->recipesRepository
            ->getRecipeCountByCategoryAndUser($category, $authorId);

        $paginator = $this->paginatorFactory->withItemCount(
            $page,
            20,
            $totalItems
        );

        $offset = $paginator->getOffset();
        $limit  = $paginator->getLength();

        $recipes = $this->recipesRepository
            ->getRecipesByCategoryAndUser($category, $authorId, $offset, $limit);

        $recipeIds = [];
        foreach ($recipes as $recipe) {
            $id = $recipe->id ?? null;

            if (is_int($id)) {
                $recipeIds[] = $id;
            } elseif (is_string($id) && ctype_digit($id)) {
                $recipeIds[] = (int) $id;
            }
        }

        $likeData = $this->likeService->getLikeDataForList(
            $viewerId,
            'recipe',
            $recipeIds
        );

        $this->template->recipes = $recipes;
        $this->template->paginator = $paginator;
        $this->template->category = $category;
        $this->template->routeCategory = $category;
        $this->template->categoryMap = $this->categoryMap;
        $this->template->authorId = $authorId;
        $this->template->viewerId = $viewerId;
        $this->template->recipeLikeCountMap = $likeData['countMap'];
        $this->template->recipeLikedMap = $likeData['likedMap'];
        $this->template->newRecipeCategories = $this->getNewRecipeCategoryMap($viewerId, $authorId);
    }


	/**
	 * @return array{breakfasts: bool, lunches: bool, snacks: bool, dinners: bool, deserts: bool}
	 */
	private function getNewRecipeCategoryMap(int $viewerId, int $authorId): array
	{
		return [
			'breakfasts' => $this->contentViewRepository->hasUnseenRecipeByCreatorAndCategory($viewerId, $authorId, 'breakfasts'),
			'lunches' => $this->contentViewRepository->hasUnseenRecipeByCreatorAndCategory($viewerId, $authorId, 'lunches'),
			'snacks' => $this->contentViewRepository->hasUnseenRecipeByCreatorAndCategory($viewerId, $authorId, 'snacks'),
			'dinners' => $this->contentViewRepository->hasUnseenRecipeByCreatorAndCategory($viewerId, $authorId, 'dinners'),
			'deserts' => $this->contentViewRepository->hasUnseenRecipeByCreatorAndCategory($viewerId, $authorId, 'deserts'),
		];
	}


    public function renderBreakfasts(int $page = 1): void
    {
        $this->setMeta(
            (string) $this->translator->translate('recipes.meta.breakfast.title'),
            (string) $this->translator->translate('recipes.meta.breakfast.description')
        );

        $this->renderCategory('breakfasts', $page);
    }


    public function renderLunches(int $page = 1): void
    {
        $this->setMeta(
            (string) $this->translator->translate('recipes.meta.lunch.title'),
            (string) $this->translator->translate('recipes.meta.lunch.description')
        );

        $this->renderCategory('lunches', $page);
    }


    public function renderDinners(int $page = 1): void
    {
        $this->setMeta(
            (string) $this->translator->translate('recipes.meta.dinner.title'),
            (string) $this->translator->translate('recipes.meta.dinner.description')
        );

        $this->renderCategory('dinners', $page);
    }


    public function renderSnacks(int $page = 1): void
    {
        $this->setMeta(
            (string) $this->translator->translate('recipes.meta.snacks.title'),
            (string) $this->translator->translate('recipes.meta.snacks.description')
        );

        $this->renderCategory('snacks', $page);
    }


    public function renderDeserts(int $page = 1): void
    {
        $this->setMeta(
            (string) $this->translator->translate('recipes.meta.desserts.title'),
            (string) $this->translator->translate('recipes.meta.desserts.description')
        );

        $this->renderCategory('deserts', $page);
    }


    public function actionDetail(string $category, int $id): void
    {
        $recipe = $this->recipesRepository->getRecipeByIdForViewer($id);

        if (!$recipe) {
            $this->flashT('recipes.messages.notFound', [], 'danger');
            $this->redirect('Recipes:breakfasts');
        }

        $viewerId = $this->getUserId();
        $authorId = $this->getRecipeAuthorId($recipe->user_id ?? null);
        $recipeCategory = is_string($recipe->category ?? null) ? $recipe->category : '';

        if (
            $authorId === null
            || $recipeCategory !== $category
            || !$this->canViewAuthorContent($viewerId, $authorId)
        ) {
            $this->flashT('recipes.messages.notFound', [], 'danger');
            $this->redirect('Recipes:breakfasts');
        }

		$this->contentViewRepository->markRecipeSeen($viewerId, $authorId, $id);

        $likeData = $this->likeService->getLikeDataForDetail(
            $viewerId,
            'recipe',
            $id
        );

        $this->setMeta(
            (string) $this->translator->translate('recipes.detail.meta.title', ['title' => $recipe->title]),
            (string) $this->translator->translate('recipes.detail.meta.description')
        );

        $this->template->recipe = $recipe;
        $this->template->category = $category;
        $this->template->categoryMap = $this->categoryMap;
        $this->template->routeCategory = $category;
        $this->template->recipeLikeCount = $likeData['count'];
        $this->template->recipeLiked = $likeData['liked'];
    }


    public function actionCreate(?int $id = null, ?string $category = null): void
    {
        if (!$this->canManageCreatorContent()) {
            $this->flashT('general.messages.notAllowed', [], 'danger');
            $this->redirect('Recipes:breakfasts');
        }

        $userId = $this->getUserId();

        if ($id === null) {
            $this->setMeta(
                (string) $this->translator->translate('recipes.recipeCreate.meta.title'),
                (string) $this->translator->translate('recipes.recipeCreate.meta.description')
            );

            $this->template->pageTitle = $this->translator->translate('recipes.create');
            $this->template->isEdit = false;
            $this->template->entity = null;
            $this->template->category = $category;
        } else {
            $recipe = $this->recipesRepository->getRecipeByIdForUser($id, $userId);
            if (!$recipe) {
                $this->flashT('recipes.messages.notFound', [], 'danger');
                $this->redirect('Recipes:breakfasts');
            }

            $this->setMeta(
                (string) $this->translator->translate('recipes.recipeEdit.meta.title', [
                    'title' => $recipe->title,
                ]),
                (string) $this->translator->translate('recipes.recipeEdit.meta.description')
            );

            $this['recipesForm']->setDefaults($recipe->toArray());
            $this->template->pageTitle = $this->translator->translate('recipes.edit');
            $this->template->isEdit = true;
            $this->template->entity = $recipe;
            $this->template->category = $this->categoryMap[$recipe->category] ?? $recipe->category;
        }

        $this->template->form = $this['recipesForm'];
        $this->template->categoryMap = $this->categoryMap;
    }


    public function handleEdit(string $id): void
    {
        if (!$this->canManageCreatorContent()) {
            $this->rejectCreatorContentManagement(['recipe', 'pagination', 'flashes']);
            return;
        }

        $userId = $this->getUserId();
        $id     = (int) $id;

        $recipe = $this->recipesRepository->getRecipeByIdForUser($id, $userId);

        if ($recipe) {
            $this['recipesForm']->setValues($recipe->toArray());
            $this->template->entity = $recipe;
        } else {
            $this->flashT('recipes.messages.notFound', [], 'danger');
        }

        $this->ajaxRedirectSnippets(['recipe', 'pagination', 'flashes']);
    }


    public function handleDelete(int $id): void
    {
        if (!$this->canManageCreatorContent()) {
            $this->rejectCreatorContentManagement(['recipe', 'pagination', 'flashes']);
            return;
        }

        $userId = $this->getUserId();

        $this->processSubmit(
            action: fn() => $this->recipesService->deleteForUser($id, $userId),
            successKey: 'recipes.messages.deleteSuccess',
            failKey: 'recipes.messages.deleteFailed',
            failParams: [],
            snippets: ['recipe', 'pagination', 'flashes'],
            appendExceptionMessageOnFail: false,
        );
    }


    protected function createComponentRecipesForm(): Form
    {
        $id     = $this->getParameter('id');
        $isEdit = $id !== null;

        $form = $this->recipesForm->create($isEdit);
        // @phpstan-ignore-next-line
        $form->onSuccess[] = [$this, 'recipesFormSucceeded'];

        return $form;
    }


    /**
     * @param ArrayHash|array<string, mixed> $values
     */
    public function recipesFormSucceeded(Form $form, $values): void
    {
        if (!$this->canManageCreatorContent()) {
            $this->rejectCreatorContentManagement(['flashes']);
            return;
        }

        $userId = $this->getUserId();

        $this->processSubmit(
            action: fn() => $this->recipesService->saveFromForm($userId, $values),
            successKey: '',
            onSuccess: function (int $recipeId) use ($values): void {
                $isUpdate = false;
                $idRaw = ($values instanceof ArrayHash) ? ($values->id ?? null) : ($values['id'] ?? null);
                if ($idRaw !== null && $idRaw !== '' && is_numeric($idRaw)) {
                    $isUpdate = true;
                }

                $this->flashT(
                    $isUpdate ? 'recipes.messages.updateSuccess' : 'recipes.messages.insertSuccess',
                    [],
                    'success'
                );

                $data = $values instanceof ArrayHash ? iterator_to_array($values) : $values;
                $categoryValue = $data['category'] ?? null;
                $category = is_string($categoryValue) ? $categoryValue : '';

                $this->redirect('Recipes:detail', [
                    'category' => $category,
                    'id'       => $recipeId,
                ]);
            },
            failKey: 'recipes.messages.saveFailed',
            snippets: ['flashes'],
            appendExceptionMessageOnFail: false,
        );
    }


    protected function requiresLogin(): bool
    {
        return true;
    }


    private function getRecipeAuthorId(mixed $authorId): ?int
    {
        if (is_int($authorId)) {
            return $authorId;
        }

        if (is_string($authorId) && ctype_digit($authorId)) {
            return (int) $authorId;
        }

        return null;
    }


    private function canViewAuthorContent(int $viewerId, int $authorId): bool
    {
        $isAdminOrSuperadmin = $this->getUser()->isLoggedIn()
            && ($this->getUser()->isInRole('admin') || $this->getUser()->isInRole('superadmin'));

        return $this->dashboardService->canViewProfile($viewerId, $authorId, $isAdminOrSuperadmin);
    }
}
