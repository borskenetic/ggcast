<?php

namespace App\Console\Commands;

use App\Services\ReceiptPrinterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class WatchReceiptPrinterCommand extends Command
{
    protected $signature = 'receipt:watch
                            {--url= : Live site URL, e.g. https://ggcast.pantas.org}
                            {--interval=2 : Seconds between checks}';

    protected $description = 'On the laptop with the USB printer: pull checkout receipts from the live OPAC and print them';

    public function handle(ReceiptPrinterService $printer): int
    {
        if (! $printer->isConfigured()) {
            $this->error('Share the USB printer in Windows as POS-80 first, then run this again.');

            return self::FAILURE;
        }

        $base = rtrim((string) ($this->option('url') ?: config('printing.poll_url') ?: config('app.url')), '/');
        if ($base === '' || str_contains($base, 'localhost') || str_contains($base, '127.0.0.1') || str_contains($base, '46.17.172.104')) {
            $base = 'https://ggcast.pantas.org';
        }
        if (! str_starts_with($base, 'http')) {
            $base = 'https://'.$base;
        }

        $token = (string) config('printing.agent_token', '');
        $interval = max(1, (int) $this->option('interval'));

        if ($token === '') {
            $this->error('Set RECEIPT_PRINT_AGENT_TOKEN in .env (same value as on the live site).');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('========================================');
        $this->info('  GGCAST Receipt Printer is RUNNING');
        $this->info('========================================');
        $this->line('Website : '.$base);
        $this->line('Printer : '.$printer->describeTarget());
        $this->line('PHP curl : '.(extension_loaded('curl') ? 'yes' : 'no (using built-in HTTPS)'));
        $this->line('Network : Wi-Fi or Ethernet — both work.');
        $this->newLine();
        $this->line('Keep this window open.');
        $this->line('When someone checks out on the tablet, a slip prints here.');
        $this->line('Do not close this window. Minimize it if you need the screen.');
        $this->newLine();

        $lastHeartbeat = 0;

        while (true) {
            try {
                $response = $this->request($token, 'get', $base.'/api/receipt-jobs/next');

                if ((int) $response['status'] === 403) {
                    $this->error('Token rejected by '.$base.'. Check RECEIPT_PRINT_AGENT_TOKEN matches the live site.');

                    return self::FAILURE;
                }

                $job = is_array($response['json'] ?? null) ? ($response['json']['job'] ?? null) : null;
                if (is_array($job) && ! empty($job['id'])) {
                    $id = $job['id'];
                    $this->line(now()->format('H:i:s').' Printing job #'.$id.' …');

                    $ok = $printer->printCheckout(
                        is_array($job['student'] ?? null) ? $job['student'] : [],
                        is_array($job['books'] ?? null) ? $job['books'] : [],
                    );

                    if ($ok) {
                        $this->request($token, 'post', $base.'/api/receipt-jobs/'.$id.'/printed');
                        $this->info(now()->format('H:i:s').' Printed job #'.$id);
                    } else {
                        $this->request($token, 'post', $base.'/api/receipt-jobs/'.$id.'/release');
                        $this->warn(now()->format('H:i:s').' Print failed for job #'.$id.'. Will retry.');
                    }
                } elseif (time() - $lastHeartbeat >= 30) {
                    $this->line(now()->format('H:i:s').' Waiting for checkouts…');
                    $lastHeartbeat = time();
                }
            } catch (Throwable $e) {
                $this->warn(now()->format('H:i:s').' Network hiccup: '.$e->getMessage());
            }

            sleep($interval);
        }
    }

    /**
     * @return array{status: int, json: mixed}
     */
    private function request(string $token, string $method, string $url): array
    {
        $urls = [$url];
        $httpUrl = (string) preg_replace('#^https:#i', 'http:', $url);
        if ($httpUrl !== $url) {
            $urls[] = $httpUrl;
        }

        $last = null;

        foreach ($urls as $tryUrl) {
            if (extension_loaded('curl')) {
                try {
                    return $this->curlRequest($token, $method, $tryUrl);
                } catch (Throwable $e) {
                    $last = $e;
                }
            }

            try {
                return $this->streamRequest($token, $method, $tryUrl);
            } catch (Throwable $e) {
                $last = $e;
            }
        }

        throw $last ?? new RuntimeException('Could not reach the live site.');
    }

    /**
     * @return array{status: int, json: mixed}
     */
    private function curlRequest(string $token, string $method, string $url): array
    {
        $pending = Http::withoutVerifying()
            ->timeout(15)
            ->connectTimeout(8)
            ->withHeaders(['X-Receipt-Token' => $token])
            ->acceptJson()
            ->withUserAgent('GGCAST-ReceiptPrinter/1.0');

        $response = strtolower($method) === 'post'
            ? $pending->post($url)
            : $pending->get($url);

        return [
            'status' => $response->status(),
            'json' => $response->json(),
        ];
    }

    /**
     * @return array{status: int, json: mixed}
     */
    private function streamRequest(string $token, string $method, string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method) === 'POST' ? 'POST' : 'GET',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'X-Receipt-Token: '.$token,
                    'User-Agent: GGCAST-ReceiptPrinter/1.0',
                    'Content-Length: 0',
                ]),
                'timeout' => 15,
                'ignore_errors' => true,
                'follow_location' => 1,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            $error = error_get_last();

            throw new RuntimeException($error['message'] ?? 'Could not open '.$url);
        }

        $status = 200;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('/HTTP\/\S+\s+(\d{3})/', $line, $match)) {
                $status = (int) $match[1];
            }
        }

        return [
            'status' => $status,
            'json' => json_decode($body, true),
        ];
    }
}
