<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UserMessageException;
use App\Repositories\RecipesRepository;
use Nette\Http\FileUpload;
use Nette\Utils\ArrayHash;

final class RecipesService
{
    public function __construct(
        private readonly RecipesRepository $recipesRepository,
        private readonly ImageService $imageService,
    ) {}


    /**
     * Uloží recipe (insert/update) pro daného usera a vrátí ID receptu.
     *
     * @param ArrayHash|array<string, mixed> $values
     * @throws UserMessageException
     * @throws \Throwable
     */
    public function saveFromForm(int $userId, ArrayHash|array $values): int
    {
        /** @var array<string, mixed> $data */
        $data = $values instanceof ArrayHash
            ? iterator_to_array($values)
            : $values;

        unset($data['user_id']);
        $data['user_id'] = $userId;

        // required fields (multi)
        $errors = [];

        if (empty($data['title'])) {
            $errors[] = ['key' => 'recipes.missingFields.title', 'params' => []];
        }
        if (empty($data['content'])) {
            $errors[] = ['key' => 'recipes.missingFields.content', 'params' => []];
        }
        if (empty($data['category'])) {
            $errors[] = ['key' => 'recipes.missingFields.category', 'params' => []];
        }

        if ($errors !== []) {
            throw new UserMessageException('recipes.messages.missingFields', [
                'errors' => $errors,
            ]);
        }

        // EDIT? -> načíst pouze vlastní (IDOR protection)
        $existingRecipe = null;
        $idRaw = $data['id'] ?? null;

        if ($idRaw !== null && $idRaw !== '' && is_numeric($idRaw)) {
            $existingRecipe = $this->recipesRepository->getRecipeByIdForUser((int) $idRaw, $userId);
            if (!$existingRecipe) {
                throw new UserMessageException('recipes.messages.notFound');
            }
        }

        /** @var FileUpload|null $file */
        $file = $data['image_path'] ?? null;

        $removeRaw       = $data['remove_image'] ?? null;
        $removeRequested = ($removeRaw === '1' || $removeRaw === 1);

        $deleteOldAfterSave = false;

        // upload new
        if ($file instanceof FileUpload) {
            if (!$file->isOk()) {
                $error = $file->getError();

                if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                    throw new UserMessageException('recipes.messages.imageTooLarge');
                }

                if ($error !== UPLOAD_ERR_NO_FILE) {
                    throw new UserMessageException('recipes.messages.invalidImage');
                }
            }

            if ($file->hasFile()) {
                try {
                    /** @var array{original:string, thumb_path:string, thumb_webp:string} $saved */
                    $saved = $this->imageService->save($file, $userId, 'recipe');
                } catch (\RuntimeException $e) {
                    $message = $e->getMessage();

                    if ($message === 'Image too large.') {
                        throw new UserMessageException('recipes.messages.imageTooLarge');
                    }

                    if ($message === 'Unsupported image type.') {
                        throw new UserMessageException('recipes.messages.invalidImage');
                    }

                    if ($message === 'Image is empty.') {
                        throw new UserMessageException('recipes.messages.invalidImage');
                    }

                    if ($message === 'No image file was uploaded.') {
                        throw new UserMessageException('recipes.messages.imageRequired');
                    }

                    if ($message === 'Image upload failed.') {
                        throw new UserMessageException('recipes.messages.invalidImage');
                    }

                    throw new UserMessageException('recipes.messages.invalidImage');
                } catch (\Throwable) {
                    throw new UserMessageException('recipes.messages.invalidImage');
                }

                $data['image_path'] = $saved['original'];
                $data['thumb_path'] = $saved['thumb_path'];
                $data['thumb_webp'] = $saved['thumb_webp'];

                if ($existingRecipe) {
                    $deleteOldAfterSave = true;
                }
            } else {
                if ($existingRecipe) {
                    if ($removeRequested) {
                        throw new UserMessageException('recipes.messages.imageRequired');
                    }

                    $data['image_path'] = $existingRecipe->image_path ?? null;
                    $data['thumb_path'] = $existingRecipe->thumb_path ?? null;
                    $data['thumb_webp'] = $existingRecipe->thumb_webp ?? null;
                } else {
                    throw new UserMessageException('recipes.messages.imageRequired');
                }
            }
        } else {
            if ($existingRecipe) {
                if ($removeRequested) {
                    throw new UserMessageException('recipes.messages.imageRequired');
                }

                $data['image_path'] = $existingRecipe->image_path ?? null;
                $data['thumb_path'] = $existingRecipe->thumb_path ?? null;
                $data['thumb_webp'] = $existingRecipe->thumb_webp ?? null;
            } else {
                throw new UserMessageException('recipes.messages.imageRequired');
            }
        }

        if (empty($data['image_path'])) {
            throw new UserMessageException('recipes.messages.imageRequired');
        }

        unset($data['remove_image']);

        // save DB
        if ($existingRecipe) {
            $this->recipesRepository->updateRecipeForUser($data, $userId);

            if ($deleteOldAfterSave) {
                $this->imageService->deleteSet($existingRecipe);
            }

            $idRaw2  = $data['id'] ?? null;
            $recipeId = is_numeric($idRaw2) ? (int) $idRaw2 : null;

            if ($recipeId === null) {
                throw new \RuntimeException('Recipe ID missing after update.');
            }

            return $recipeId;
        }

        $newRecipe = $this->recipesRepository->insertRecipeForUser($data, $userId);

        $idRaw2  = $newRecipe->id ?? null;
        $recipeId = is_numeric($idRaw2) ? (int) $idRaw2 : null;

        if ($recipeId === null) {
            throw new \RuntimeException('Recipe ID missing after insert.');
        }

        return $recipeId;
    }


    /**
     * Smaže recipe pro daného usera včetně souborů.
     *
     * @throws \Throwable
     */
    public function deleteForUser(int $id, int $userId): void
    {
        $recipe = $this->recipesRepository->getRecipeByIdForUser($id, $userId);

        if ($recipe) {
            $this->imageService->deleteSet($recipe);
        }

        $this->recipesRepository->deleteRecipeByIdForUser($id, $userId);
    }
}
