<?php
declare(strict_types=1);

namespace App\Domain\Payment\Repository;

use App\Domain\Payment\PaymentWebhookSettings;

interface PaymentWebhookSettingsRepositoryInterface
{
    public function get(): ?PaymentWebhookSettings;

    public function getOrCreate(): PaymentWebhookSettings;

    public function save(PaymentWebhookSettings $settings): void;
}
