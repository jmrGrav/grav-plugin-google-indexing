<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Page\Interfaces\PageInterface;
use RocketTheme\Toolbox\Event\Event;

class GoogleIndexingPlugin extends Plugin
{
    const EXCLUDED_ROUTES = ['/', '/fr', '/en', '/privacy-policies'];

    public static function getSubscribedEvents(): array
    {
        return [
            'onAdminAfterSave' => ['onAdminAfterSave', 0],
            'onMcpAfterSave'   => ['onMcpAfterSave', 0],
        ];
    }

    public function onAdminAfterSave(Event $event): void
    {
        $object = $event['page'] ?? $event['object'] ?? null;
        if (!$object || !($object instanceof PageInterface)) return;
        if (method_exists($object, 'published') && !$object->published()) return;

        if (method_exists($object, 'route')) {
            $route = $object->route();
        } elseif (method_exists($object, 'getRoute')) {
            $route = $object->getRoute();
        } else {
            return;
        }

        if (!$route) return;
        if (in_array($route, self::EXCLUDED_ROUTES, true)) return;
        if (str_starts_with($route, '/tag')) return;

        $this->handleRoute($route);
    }

    public function onMcpAfterSave(Event $event): void
    {
        $route = $event['route'] ?? null;
        if (!$route) return;
        if (in_array($route, self::EXCLUDED_ROUTES, true)) return;
        if (str_starts_with($route, '/tag')) return;

        $this->handleRoute($route);
    }

    private function handleRoute(string $route): void
    {
        $config = $this->grav['config'];
        $host   = $config->get('plugins.google-indexing.host', 'arleo.eu');

        $urls = [
            "https://{$host}{$route}",
            "https://{$host}/fr{$route}",
            "https://{$host}/en{$route}",
        ];

        $token = $this->getGoogleAccessToken();
        if (!$token) {
            $this->grav['log']->error('[GoogleIndexing] Impossible d\'obtenir un access token.');
            return;
        }

        foreach ($urls as $url) {
            $this->submitToGoogle($url, $token);
        }
    }

    private function getGoogleAccessToken(): ?string
    {
        $config  = $this->grav['config'];
        $keyFile = $config->get('plugins.google-indexing.key_file', '/etc/grav-google-indexing/service-account.json');

        if (!file_exists($keyFile)) {
            $this->grav['log']->error("[GoogleIndexing] Fichier service account introuvable : {$keyFile}");
            return null;
        }

        $credentials = json_decode(file_get_contents($keyFile), true);
        if (!$credentials || empty($credentials['private_key']) || empty($credentials['client_email'])) {
            $this->grav['log']->error('[GoogleIndexing] Fichier service account invalide ou incomplet.');
            return null;
        }

        $privateKey  = $credentials['private_key'];
        $clientEmail = $credentials['client_email'];
        $now         = time();

        $header  = $this->base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64url(json_encode([
            'iss'   => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/indexing',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ]));

        $toSign = $header . '.' . $payload;
        if (!openssl_sign($toSign, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            $this->grav['log']->error('[GoogleIndexing] Échec de la signature JWT : ' . openssl_error_string());
            return null;
        }

        $jwt = $toSign . '.' . $this->base64url($signature);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = json_decode(curl_exec($ch), true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($response['access_token'])) {
            $this->grav['log']->error('[GoogleIndexing] Échec OAuth : HTTP ' . $httpCode . ' — ' . json_encode($response));
            return null;
        }

        return $response['access_token'];
    }

    private function submitToGoogle(string $url, string $accessToken): void
    {
        $ch = curl_init('https://indexing.googleapis.com/v3/urlNotifications:publish');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['url' => $url, 'type' => 'URL_UPDATED']),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$accessToken}",
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->grav['log']->error("[GoogleIndexing] Erreur cURL : {$error}");
            return;
        }

        if ($httpCode === 200) {
            $this->grav['log']->info("[GoogleIndexing] ✅ OK {$url}");
        } else {
            $this->grav['log']->warning("[GoogleIndexing] ⚠️ HTTP {$httpCode} {$url} — {$response}");
        }
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
