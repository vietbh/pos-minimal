<?php

declare(strict_types=1);

namespace App\Application\Order\Query\ListOrders;

use App\Domain\Order\Enum\OrderStatus;

final readonly class ListOrdersInput
{
    public function __construct(
        public string $search = '',
        public ?OrderStatus $status = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {
        if ($this->page <= 0) {
            throw new \InvalidArgumentException('Page must be greater than zero.');
        }

        if ($this->perPage <= 0 || $this->perPage > 50) {
            throw new \InvalidArgumentException('Per-page value must be between 1 and 50.');
        }

        if (preg_match('/^.{101,}$/us', $this->search) === 1) {
            throw new \InvalidArgumentException('Search query cannot exceed 100 characters.');
        }

        if ($this->from !== null && $this->to !== null && $this->from > $this->to) {
            throw new \InvalidArgumentException('From date cannot be after to date.');
        }
    }
}
