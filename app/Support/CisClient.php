<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

class CisClient
{
    protected string $base;
    protected int $timeout;
    protected string $authPath;
    protected ?string $token = null;

    public function __construct()
    {
        $this->base     = rtrim(config('services.ext_api.base_url', env('API_BASE_URL')), '/');
        $this->timeout  = (int) config('services.ext_api.timeout', env('API_TIMEOUT', 15));
        $this->authPath = env('CIS_AUTH_PATH', '/jwt-api/do-auth');
    }

    public function login(): void
    {
        $resp = Http::baseUrl($this->base)
            ->acceptJson()->asForm()->timeout($this->timeout)
            ->post($this->authPath, [
                'username' => env('CIS_USERNAME'),
                'password' => env('CIS_PASSWORD'),
            ]);

        $body = $resp->json();
        $ok   = $resp->ok() && (data_get($body,'result') === true || data_get($body,'result') === 1);

        if (!$ok) {
            throw new \RuntimeException('CIS auth failed: '.(data_get($body,'message') ?? data_get($body,'error') ?? $resp->body()));
        }

        $this->token = data_get($body,'token')
                    ?? data_get($body,'access_token')
                    ?? data_get($body,'data.token')
                    ?? data_get($body,'data.access_token');

        if (!$this->token) {
            throw new \RuntimeException('CIS token not found in auth response.');
        }
    }

    public function get(string $path, array $query = []): array
    {
        if (!$this->token) $this->login();

        $do = function () use ($path, $query) {
            return Http::baseUrl($this->base)
                ->acceptJson()
                ->timeout($this->timeout)
                ->retry(3, 500)
                ->withToken($this->token)
                ->get($path, $query);
        };

        $resp = $do();

        if ($resp->status() === 401) {
            $this->login();
            $resp = $do();
        }

        if (!$resp->ok()) {
            $url = rtrim($this->base,'/').$path;
            throw new \RuntimeException("CIS GET {$url} failed (HTTP {$resp->status()}): ".$resp->body());
        }

        return $resp->json();
    }

    /** GET memakai token yang sudah kamu miliki (mis. dari /do-auth di controller) */
    public function getWithToken(string $token, string $path, array $query = []): array
    {
        $resp = Http::baseUrl($this->base)
            ->acceptJson()
            ->timeout($this->timeout)
            ->retry(3, 500)
            ->withToken($token)
            ->get($path, $query);

        if (!$resp->ok()) {
            $url = rtrim($this->base,'/').$path;
            throw new \RuntimeException("CIS GET {$url} failed (HTTP {$resp->status()}): ".$resp->body());
        }

        return $resp->json();
    }

    /** Ambil semua halaman (dataKey bisa nested: 'data.dosen', 'data.pegawai', dll) */
    public function getAll(string $path, array $query = [], string $dataKey = 'data'): \Generator
    {
        $page = 1;
        while (true) {
            $payload = $this->get($path, $query + ['page' => $page, 'per_page' => ($query['per_page'] ?? 200)]);
            $rows = data_get($payload, $dataKey) ?? $payload;

            if (!is_array($rows) || count($rows) === 0) break;

            foreach ($rows as $row) yield $row;

            $hasMore = data_get($payload, 'links.next')
                    || (data_get($payload, 'meta.current_page') < data_get($payload, 'meta.last_page'));
            if (!$hasMore) break;
            $page++;
        }
    }
}
