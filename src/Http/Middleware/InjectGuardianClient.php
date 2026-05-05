<?php

namespace Brigada\Guardian\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InjectGuardianClient
{
    private const SENTINEL = '<!-- guardian-client-injected -->';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldInject($response)) {
            return $response;
        }

        $content = (string) $response->getContent();

        if ($content === '' || str_contains($content, self::SENTINEL)) {
            return $response;
        }

        $injection = $this->buildInjection($content);

        if (str_contains($content, '</head>')) {
            $injected = preg_replace('/<\/head>/i', $injection . '</head>', $content, 1);
        } elseif (str_contains($content, '</body>')) {
            $injected = preg_replace('/<\/body>/i', $injection . '</body>', $content, 1);
        } else {
            return $response;
        }

        if ($injected === null) {
            return $response;
        }

        $response->setContent($injected);

        if ($response->headers->has('Content-Length')) {
            $response->headers->set('Content-Length', (string) strlen($injected));
        }

        return $response;
    }

    private function shouldInject(Response $response): bool
    {
        if (! config('guardian.client_errors.enabled', true)) {
            return false;
        }

        if (! config('guardian.client_errors.auto_inject', true)) {
            return false;
        }

        if (app()->runningInConsole()) {
            return false;
        }

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return false;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));

        return str_contains($contentType, 'text/html');
    }

    private function buildInjection(string $existingContent): string
    {
        $scriptUrl = config('guardian.client_errors.script_url')
            ?: route('guardian.client-script');

        $endpoint = url(config('guardian.client_errors.route', 'guardian/client-errors'));
        $captureConsoleError = config('guardian.client_errors.capture_console_error', false) ? 'true' : 'false';

        $needsCsrfMeta = ! preg_match('/<meta\s+name=["\']csrf-token["\']/i', $existingContent);
        $csrfMeta = $needsCsrfMeta
            ? '<meta name="csrf-token" content="' . e(csrf_token()) . '">'
            : '';

        $configScript = sprintf(
            '<script>window.GuardianClientErrors={url:%s,captureConsoleError:%s};</script>',
            json_encode($endpoint, JSON_UNESCAPED_SLASHES),
            $captureConsoleError
        );

        return self::SENTINEL
            . $csrfMeta
            . $configScript
            . '<script src="' . e($scriptUrl) . '" defer></script>';
    }
}
