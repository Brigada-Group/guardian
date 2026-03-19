<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class SslCertificateCheck implements HealthCheck
{
    public function name(): string { return 'SSL Certificate'; }
    public function schedule(): Schedule { return Schedule::Daily; }

    public function run(): CheckResult
    {
        try {
            $url = config('app.url');
            if (! $url || ! str_starts_with($url, 'https://')) {
                return new CheckResult(Status::Ok, 'No HTTPS URL configured — skipping', ['skipped' => true]);
            }
            $host = parse_url($url, PHP_URL_HOST);
            $context = stream_context_create(['ssl' => ['capture_peer_cert' => true]]);
            $client = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
            if (! $client) {
                return new CheckResult(Status::Critical, "Could not connect to {$host}: {$errstr}");
            }
            $params = stream_context_get_params($client);
            $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
            fclose($client);
            if (! $cert || ! isset($cert['validTo_time_t'])) {
                return new CheckResult(Status::Error, 'Could not parse SSL certificate');
            }
            $expiresAt = $cert['validTo_time_t'];
            $daysUntilExpiry = (int) (($expiresAt - time()) / 86400);
            $thresholds = config('guardian.thresholds.ssl_days_before_expiry');
            $status = match (true) {
                $daysUntilExpiry <= 0 => Status::Critical,
                $daysUntilExpiry <= $thresholds['critical'] => Status::Critical,
                $daysUntilExpiry <= $thresholds['warning'] => Status::Warning,
                default => Status::Ok,
            };
            $message = $daysUntilExpiry <= 0 ? 'SSL certificate has EXPIRED' : "Expires in {$daysUntilExpiry} days";
            return new CheckResult($status, $message, ['days_until_expiry' => $daysUntilExpiry, 'expires_at' => date('Y-m-d', $expiresAt), 'host' => $host]);
        } catch (\Throwable $e) {
            return new CheckResult(Status::Error, "SSL check failed: {$e->getMessage()}");
        }
    }
}
