<?php

namespace Brigada\Guardian\Http\Controllers;

use Brigada\Guardian\Security\StackTraceSanitizer;
use Brigada\Guardian\Support\NightwatchUserPayload;
use Brigada\Guardian\Transport\NightwatchClient;
use Brigada\Guardian\Transport\SendToNightwatchClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ClientErrorController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! config('guardian.client_errors.enabled', true)) {
            return response()->json(['ok' => false], 404);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'stack' => ['nullable', 'string', 'max:16000'],
            'name' => ['nullable', 'string', 'max:255'],
            'filename' => ['nullable', 'string', 'max:2048'],
            'lineno' => ['nullable', 'integer'],
            'colno' => ['nullable', 'integer'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'component_stack' => ['nullable', 'string', 'max:8000'],
        ]);

        $client = app(NightwatchClient::class);

        if (! $client->isConfigured()) {
            return response()->json(['ok' => true, 'forwarded' => false]);
        }

        $message = StackTraceSanitizer::sanitize($validated['message']);

        $stack = isset($validated['stack']) ? StackTraceSanitizer::sanitize($validated['stack']) : '';

        $pageUrl = $validated['page_url'] ?? $request->headers->get('Referer') ?? 'N/A';

        if (is_string($pageUrl)) {
            $pageUrl = StackTraceSanitizer::sanitize(mb_substr($pageUrl, 0, 2048));
        }

        $data = NightwatchUserPayload::merge([
            'runtime' => 'javascript',
            'exception_class' => $validated['name'] ?? 'JavaScriptError',
            'message' => mb_substr($message, 0, 1000),
            'file' => isset($validated['filename'])
                ? StackTraceSanitizer::sanitize((string) $validated['filename'])
                : 'N/A',
            'line' => $validated['lineno'] ?? 0,
            'colno' => $validated['colno'] ?? null,
            'url' => 'GET ' . $pageUrl,
            'status_code' => 0,
            'ip' => $request->ip() ?? 'N/A',
            'headers' => 'User-Agent: ' . ($request->userAgent() ?? 'N/A'),
            'stack_trace' => $stack !== '' ? "```\n{$stack}\n```" : 'N/A',
            'component_stack' => isset($validated['component_stack'])
                ? StackTraceSanitizer::sanitize(mb_substr($validated['component_stack'], 0, 4000))
                : null,
            'severity' => 'error',
            'created_at' => now(),
        ]);

        $ingestEndpoint = config('guardian.client_errors.hub_ingest_endpoint', 'client-errors');

        try {
            if (config('guardian.hub.async', true)) {
                SendToNightwatchClient::dispatch($ingestEndpoint, $data);
            } else {
                $client->send($ingestEndpoint, $data);
            }
        } catch (\Throwable) {
            // never break the page
        }

        return response()->json(['ok' => true, 'forwarded' => true]);
    }
}
