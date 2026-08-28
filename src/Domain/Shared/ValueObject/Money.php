<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObject;

use InvalidArgumentException;

final readonly class Money
{
    private function __construct(
        private int $minorUnits,
    ) {
        if ($this->minorUnits < 0) {
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
     * Create Money from an integer major-unit amount.
     *
     * Example:
     * Money::fromInt(100) => 100.00
     */
    public static function fromInt(int $amount): self
    {
        if ($amount < 0) {
            throw new InvalidArgumentException(
                'Money amount cannot be negative.'
            );
        }

        return new self($amount * 100);
    }

    /**
     * Create Money from a decimal string.
     *
     * Examples:
     * "100"       => 100.00
     * "100.50"    => 100.50
     * "100.5"     => 100.50
     * "0.01"      => 0.01
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

        $fraction = str_pad($fraction, 2, '0');

        return new self(
            ((int) $whole * 100) + (int) $fraction
        );
    }

    /**
     * Return exact decimal representation for persistence.
     */
    public function toDecimal(): string
    {
        $whole = intdiv($this->minorUnits, 100);
        $fraction = $this->minorUnits % 100;

        return sprintf('%d.%02d', $whole, $fraction);
    }

    public function minorUnits(): int
    {
        return $this->minorUnits;
    }

    public function add(self $other): self
    {
        return new self(
            $this->minorUnits + $other->minorUnits
        );
    }

    public function subtract(self $other): self
    {
        if ($other->minorUnits > $this->minorUnits) {
            throw new InvalidArgumentException(
                'Money subtraction cannot result in a negative amount.'
            );
        }

        return new self(
            $this->minorUnits - $other->minorUnits
        );
    }

    public function multiply(int $multiplier): self
    {
        if ($multiplier < 0) {
            throw new InvalidArgumentException(
                'Money multiplier cannot be negative.'
            );
        }

        return new self(
            $this->minorUnits * $multiplier
        );
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    public function isPositive(): bool
    {
        return $this->minorUnits > 0;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->minorUnits > $other->minorUnits;
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->minorUnits >= $other->minorUnits;
    }

    public function isLessThan(self $other): bool
    {
        return $this->minorUnits < $other->minorUnits;
    }

    public function isLessThanOrEqual(self $other): bool
    {
        return $this->minorUnits <= $other->minorUnits;
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits;
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }
}
