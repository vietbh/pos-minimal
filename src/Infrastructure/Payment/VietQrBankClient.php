<?php
declare(strict_types=1);

namespace App\Infrastructure\Payment;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class VietQrBankClient
{
    private const BANKS_URL = 'https://api.vietqr.io/v2/banks';
    private const CACHE_KEY = 'vietqr.bank_list.v2';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @return list<array{id:int,name:string,code:string,bin:string,shortName:string,logo:string,transferSupported:bool,lookupSupported:bool}> */
    public function getBanks(): array
    {
        return $this->cache->get(self::CACHE_KEY, function ($item): array {
            $item->expiresAfter(86400);

            $response = $this->httpClient->request('GET', self::BANKS_URL, [
                'timeout' => 8,
                'headers' => ['Accept' => 'application/json'],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('VietQR bank list request failed.');
            }

            $payload = $response->toArray(false);
            if (($payload['code'] ?? null) !== '00' || !is_array($payload['data'] ?? null)) {
                throw new \RuntimeException('VietQR returned an invalid bank list.');
            }

            $banks = [];
            foreach ($payload['data'] as $bank) {
                if (!is_array($bank)) {
                    continue;
                }

                $bin = trim((string) ($bank['bin'] ?? ''));
                $name = trim((string) ($bank['name'] ?? ''));
                $shortName = trim((string) ($bank['shortName'] ?? ''));
                $code = trim((string) ($bank['code'] ?? ''));
                if ($bin === '' || $name === '' || $code === '') {
                    continue;
                }

                $banks[] = [
                    'id' => (int) ($bank['id'] ?? 0),
                    'name' => $name,
                    'code' => $code,
                    'bin' => $bin,
                    'shortName' => $shortName,
                    'logo' => trim((string) ($bank['logo'] ?? '')),
                    'transferSupported' => (bool) ($bank['transferSupported'] ?? false),
                    'lookupSupported' => (bool) ($bank['lookupSupported'] ?? false),
                ];
            }

            usort($banks, static fn (array $a, array $b): int => strcasecmp($a['shortName'] ?: $a['name'], $b['shortName'] ?: $b['name']));

            return $banks;
        });
    }
}
