<?php

declare(strict_types=1);

namespace App\Twig;

use App\Domain\Shared\ValueObject\Money;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class MoneyVndExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('money_vnd', [$this, 'format']),
        ];
    }

    public function format(Money|string|int|float|null $value): string
    {
        if ($value instanceof Money) {
            $decimal = $value->toDecimal();
        } elseif ($value === null || $value === '') {
            return '0 ₫';
        } else {
            $decimal = (string) $value;
        }

        $decimal = trim($decimal);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $decimal, $matches)) {
            return $decimal . ' ₫';
        }

        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        // VND is displayed without decimal places. Keep a non-zero
        // fractional part visible rather than silently changing its value.
        $fraction = rtrim(str_pad($fraction, 2, '0'), '0');

        $groups = [];
        while (strlen($whole) > 3) {
            $groups[] = substr($whole, -3);
            $whole = substr($whole, 0, -3);
        }
        $groups[] = $whole;

        $formatted = implode('.', array_reverse($groups));
        if ($fraction !== '') {
            $formatted .= ',' . $fraction;
        }

        return $formatted . ' ₫';
    }
}
