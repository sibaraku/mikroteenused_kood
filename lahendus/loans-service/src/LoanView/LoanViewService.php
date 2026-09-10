<?php

declare(strict_types=1);

namespace Laenutus\LoanView;

use Laenutus\Auth\AuthMiddleware;
use Laenutus\Clients\ItemsHttpClient;
use Laenutus\Loans\LoansService;

final class LoanViewService
{
    public function __construct(
        private readonly ?LoansService $loans = null,
        private readonly ?ItemsHttpClient $items = null,
    ) {}

    private function loans(): LoansService
    {
        return $this->loans ?? new LoansService();
    }

    private function items(): ItemsHttpClient
    {
        return $this->items ?? new ItemsHttpClient();
    }

    /** @param array{userId: string, role: string} $auth */
    public function get(string $loanId, array $auth, ?string $authToken = null, ?string $requestId = null): array
    {
        $loan = $this->loans()->get($loanId);
        AuthMiddleware::canAccessLoan($auth, $loan['userId']);

        $item = $this->items()->get($loan['itemId'], $authToken, $requestId);

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
