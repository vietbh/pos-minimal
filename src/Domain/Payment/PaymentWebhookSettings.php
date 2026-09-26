<?php
declare(strict_types=1);

namespace App\Domain\Payment;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payment_webhook_settings')]
class PaymentWebhookSettings
{
    public const SINGLETON_ID = 1;

    #[ORM\Id]
    #[ORM\Column(type: 'smallint', options: ['unsigned' => true])]
    private int $id = self::SINGLETON_ID;

    #[ORM\Column(name: 'webhook_token', length: 64)]
    private string $webhookToken;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(?string $webhookToken = null)
    {
        $now = new \DateTimeImmutable();
        $this->webhookToken = $webhookToken !== null && $webhookToken !== ''
            ? $webhookToken
            : bin2hex(random_bytes(32));
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getWebhookToken(): string
    {
        return $this->webhookToken;
    }

    public function regenerateWebhookToken(): string
    {
        $this->webhookToken = bin2hex(random_bytes(32));
        $this->updatedAt = new \DateTimeImmutable();

        return $this->webhookToken;
    }

    public function matchesToken(string $provided): bool
    {
        return $provided !== '' && hash_equals($this->webhookToken, $provided);
    }
}
