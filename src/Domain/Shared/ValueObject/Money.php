<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObject;

use InvalidArgumentException;

final readonly class Money
{
    private function __construct(
        private int $amount,
    ) {
        if ($this->amount < 0) {
            throw new InvalidArgumentException(
                'Money amount cannot be negative.'
            );
        }
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Create Money from an integer Vietnamese Dong amount.
     *
     * Examples:
     * Money::fromInt(100) => 100 VND
     * Money::fromInt(22000) => 22,000 VND
     */
    public static function fromInt(int $amount): self
    {
        if ($amount < 0) {
            throw new InvalidArgumentException(
                'Money amount cannot be negative.'
            );
        }

        return new self($amount);
    }

    /**
     * Create Money from a decimal persistence value.
     *
     * VND has no fractional unit, therefore the fractional part must
     * be zero. Values such as "22000" and "22000.00" are accepted.
     *
     * Examples:
     * "100"       => 100 VND
     * "100.00"    => 100 VND
     * "22000.00"  => 22,000 VND
     */
    public static function fromDecimal(string $amount): self
    {
        $amount = trim($amount);

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException(
                'Money amount must be a non-negative decimal with up to 2 decimal places.'
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $amount, 2),
            2,
            ''
        );

        if ($fraction !== '' && preg_match('/[^0]/', $fraction)) {
            throw new InvalidArgumentException(
                'Vietnamese Dong does not support fractional amounts.'
            );
        }

        return new self((int) $whole);
    }

    /**
     * Return the exact decimal representation used by persistence.
     *
     * Example:
     * 22000 => "22000.00"
     */
    public function toDecimal(): string
    {
        return sprintf('%d.00', $this->amount);
    }

    /**
     * Return the integer VND amount.
     */
    public function minorUnits(): int
    {
        return $this->amount;
    }

    public function add(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function subtract(self $other): self
    {
        if ($other->amount > $this->amount) {
            throw new InvalidArgumentException(
                'Money subtraction cannot result in a negative amount.'
            );
        }

        return new self($this->amount - $other->amount);
    }

    public function multiply(int $multiplier): self
    {
        if ($multiplier < 0) {
            throw new InvalidArgumentException(
                'Money multiplier cannot be negative.'
            );
        }

        return new self($this->amount * $multiplier);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->amount > $other->amount;
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->amount >= $other->amount;
    }

    public function isLessThan(self $other): bool
    {
        return $this->amount < $other->amount;
    }

    public function isLessThanOrEqual(self $other): bool
    {
        return $this->amount <= $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }
}
