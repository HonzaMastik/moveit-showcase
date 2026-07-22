<?php

declare(strict_types=1);

namespace App\Repositories;

use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class RecipesRepository extends BaseRepository
{
    /**
     * ČTENÍ pro viewer (detail receptu na profilu tvůrce).
     * Pozor: nepoužívat pro edit/delete.
     */
    public function getRecipeByIdForViewer(int $id): ?ActiveRow
    {
        $row = $this->database->table('moveit_recipes')
            ->where('id', $id)
            ->where('deleted', 0)
            ->fetch();

        return $row instanceof ActiveRow ? $row : null;
    }
    

    /**
     * ČTENÍ pro ownera (editace/smazání jen vlastních receptů).
     */
    public function getRecipeByIdForUser(int $id, int $userId): ?ActiveRow
    {
        $row = $this->database->table('moveit_recipes')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->where('deleted', 0)
            ->fetch();

        return $row instanceof ActiveRow ? $row : null;
    }


    /**
     * UPDATE jen vlastního receptu (IDOR protection).
     *
     * @param array<string, mixed> $values
     */
    public function updateRecipeForUser(array $values, int $userId): bool
    {
        if (!array_key_exists('id', $values)) {
            throw new \InvalidArgumentException('Recipe ID is missing.');
        }
        if (!is_numeric($values['id'])) {
            throw new \InvalidArgumentException('Recipe ID must be numeric.');
        }

        $recipeId = (int) $values['id'];
        if ($recipeId <= 0) {
            throw new \InvalidArgumentException('Recipe ID must be a positive integer.');
        }

        $recipe = $this->getRecipeByIdForUser($recipeId, $userId);
        if (!$recipe instanceof ActiveRow) {
            throw new \RuntimeException('Recipe entry not found.');
        }

        unset($values['id'], $values['remove_image'], $values['user_id'], $values['deleted']);

        $allowed = [
            'title',
            'content',
            'category',
            'image_path',
            'thumb_path',
            'thumb_webp',
            'updated_at',
        ];

        $values = array_intersect_key($values, array_flip($allowed));

        return (bool) $recipe->update($values);
    }


    /**
     * Soft delete jen vlastního receptu (IDOR protection).
     */
    public function deleteRecipeByIdForUser(int $id, int $userId): bool
    {
        $recipe = $this->getRecipeByIdForUser($id, $userId);
        if (!$recipe instanceof ActiveRow) {
            throw new \RuntimeException('Recipe entry not found.');
        }

        return (bool) $recipe->update(['deleted' => 1]);
    }


    public function getRecipeCountByCategoryAndUser(string $category, int $userId): int
    {
        return $this->database->table('moveit_recipes')
            ->where('category', $category)
            ->where('user_id', $userId)
            ->where('deleted', 0)
            ->count('*');
    }


    /**
     * @param int<0, max> $offset
     * @param int<0, max> $limit
     */
    public function getRecipesByCategoryAndUser(
        string $category,
        int $userId,
        int $offset = 0,
        int $limit = 10
    ): Selection {
        return $this->database->table('moveit_recipes')
            ->select('id, user_id, category, title, thumb_path, thumb_webp, created_at')
            ->where('category', $category)
            ->where('user_id', $userId)
            ->where('deleted', 0)
            ->order('created_at DESC')
            ->limit($limit, $offset);
    }


    /**
     * INSERT jen pod přihlášeného uživatele (owner).
     *
     * @param array<string, mixed> $values
     */
    public function insertRecipeForUser(array $values, int $userId): ActiveRow
    {
        unset($values['id'], $values['remove_image'], $values['user_id'], $values['deleted']);

        $allowedCategories = ['breakfasts', 'lunches', 'dinners', 'snacks', 'deserts'];

        $category = $values['category'] ?? null;
        if (!is_string($category) || !in_array($category, $allowedCategories, true)) {
            throw new \InvalidArgumentException('Invalid category.');
        }

        $values['user_id'] = $userId;
        $values['deleted'] = 0;

        $allowed = [
            'user_id',
            'category',
            'title',
            'content',
            'image_path',
            'thumb_path',
            'thumb_webp',
            'created_at',
            'updated_at',
            'deleted',
        ];

        $values = array_intersect_key($values, array_flip($allowed));

        $row = $this->database->table('moveit_recipes')->insert($values);

        if (!$row instanceof ActiveRow) {
            throw new \RuntimeException('Insert recipe failed.');
        }

        return $row;
    }
}
