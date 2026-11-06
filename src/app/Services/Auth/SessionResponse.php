<?php
namespace App\Kitchen\app\Services\Auth;

use Symfony\Component\HttpFoundation\{JsonResponse,Response};

final class SessionResponse
{
    public static function create(array $server, bool $temporary, string $root): Response
    {
        $ajax = strtolower($server['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest' ||
            str_contains($server['HTTP_ACCEPT'] ?? '', 'application/json') ||
            str_starts_with(trim($_GET['url'] ?? '', '/'), 'ajax/');
        $code = $temporary ? 'KITCHEN_SESSION_REFRESH_UNAVAILABLE' : 'KITCHEN_AUTH_REQUIRED';
        $status = $temporary ? 503 : 401;
        $headers = [
            'Cache-Control' => 'private, no-store',
            'X-Kitchen-Auth' => $temporary ? 'unavailable' : 'required',
        ];
        if ($temporary) $headers['Retry-After'] = '30';
        if ($ajax) return new JsonResponse([
            'success' => false, 'description' => $code, 'login_url' => $root . '/auth',
        ], $status, $headers);
        if (!$temporary) return new Response('', 302, $headers + ['Location' => $root . '/auth']);

        $messages = [
            'pl' => 'Nie udało się chwilowo odświeżyć sesji. Odśwież stronę za chwilę.',
            'en' => 'The session could not be refreshed temporarily. Please reload shortly.',
            'fr' => 'Impossible de renouveler la session pour le moment. Rechargez la page dans un instant.',
            'it' => 'Impossibile aggiornare la sessione al momento. Ricarica la pagina tra poco.',
            'nl' => 'De sessie kon tijdelijk niet worden vernieuwd. Laad de pagina zo opnieuw.',
        ];
        $language = strtolower(substr($server['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2));
        return new Response(
            '<p>' . htmlspecialchars($messages[$language] ?? $messages['en'], ENT_QUOTES, 'UTF-8') . '</p>',
            $status, $headers + ['Content-Type' => 'text/html; charset=utf-8']
        );
    }
}
