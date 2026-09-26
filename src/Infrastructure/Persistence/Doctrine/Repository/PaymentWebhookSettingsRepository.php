<?php
declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Payment\PaymentWebhookSettings;
use App\Domain\Payment\Repository\PaymentWebhookSettingsRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class PaymentWebhookSettingsRepository implements PaymentWebhookSettingsRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function get(): ?PaymentWebhookSettings
    {
        return $this->em->find(PaymentWebhookSettings::class, PaymentWebhookSettings::SINGLETON_ID);
    }

    public function getOrCreate(): PaymentWebhookSettings
    {
        $settings = $this->get();
        if ($settings instanceof PaymentWebhookSettings) {
            return $settings;
        }

        $settings = new PaymentWebhookSettings();
        $this->save($settings);

        return $settings;
    }

    public function save(PaymentWebhookSettings $settings): void
    {
        $this->em->persist($settings);
        $this->em->flush();
    }
}
