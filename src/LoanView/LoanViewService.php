<?php

declare(strict_types=1);

namespace Laenutus\LoanView;

use Laenutus\Auth\AuthException;
use Laenutus\Auth\AuthMiddleware;
use Laenutus\Items\ItemsService;
use Laenutus\Loans\LoansService;

final class LoanViewService
{
    public function __construct(
        private readonly ?LoansService $loans = null,
        private readonly ?ItemsService $items = null,
    ) {}

    private function loans(): LoansService
    {
        return $this->loans ?? new LoansService();
    }

    private function items(): ItemsService
    {
        return $this->items ?? new ItemsService();
    }

    /** @param array{userId: string, role: string} $auth */
    public function get(string $loanId, array $auth): array
    {
        $loan = $this->loans()->get($loanId);
        AuthMiddleware::canAccessLoan($auth, $loan['userId']);

        $item = $this->items()->findRaw($loan['itemId']);
        if ($item === null) {
            throw new AuthException('ITEM_NOT_FOUND', 'Vahendit ei leitud', 404);
        }

        return [
            'loanId' => $loan['id'],
            'loanStatus' => $loan['status'],
            'userId' => $loan['userId'],
            'itemId' => $loan['itemId'],
            'itemName' => $item['name'],
            'itemStatus' => $item['status'],
            'startDate' => $loan['startDate'],
            'endDate' => $loan['endDate'],
        ];
    }
}
