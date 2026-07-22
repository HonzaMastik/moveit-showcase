<?php

declare(strict_types=1);

namespace App\Utils;

use Nette\Utils\Paginator;

final class PaginatorFactory
{
    public function create(int $page, int $itemsPerPage): Paginator
    {
        $paginator = new Paginator();

        $paginator->setItemsPerPage(max(1, $itemsPerPage));
        $paginator->setPage(max(1, $page));

        return $paginator;
    }

    public function withItemCount(
        int $page,
        int $itemsPerPage,
        int $itemCount
    ): Paginator {
        $paginator = $this->create($page, $itemsPerPage);
        $paginator->setItemCount(max(0, $itemCount));

        return $paginator;
    }
}
