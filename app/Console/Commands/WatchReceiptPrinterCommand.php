<?php

namespace App\Console\Commands;

use App\Services\ReceiptPrinterService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class WatchReceiptPrinterCommand extends Command
{
    protected $signature = 'receipt:watch
                            {--url= : Live site URL, e.g. https://ggcast.pantas.org}
                            {--interval=2 : Seconds between checks}';

    protected $description = 'On the laptop with the USB printer: pull checkout receipts from the live OPAC and print them';

    private ?array $workingAttempt = null;

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

                if ($response->status() === 403) {
                    $this->error('Token rejected by '.$base.'. Check RECEIPT_PRINT_AGENT_TOKEN matches the live site.');

                    return self::FAILURE;
                }

                $job = $response->json('job');
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
                $this->workingAttempt = null;
                $this->warn(now()->format('H:i:s').' Network hiccup: '.$e->getMessage());
                $this->warn('Cannot reach '.$base.' yet. The tablet website can still work; this PC needs a path to the internet.');
            }

            sleep($interval);
        }
    }

    private function request(string $token, string $method, string $httpsUrl): Response
    {
        $attempts = $this->workingAttempt ? [$this->workingAttempt] : $this->connectionAttempts($httpsUrl);
        $last = null;

        foreach ($attempts as $attempt) {
            try {
                $pending = $this->client($token, $attempt['options']);
                $response = $method === 'post'
                    ? $pending->post($attempt['url'])
                    : $pending->get($attempt['url']);

                $this->workingAttempt = $attempt;

                return $response;
            } catch (Throwable $e) {
                $last = $e;
            }
        }

        throw $last ?? new \RuntimeException('Could not reach the live site.');
    }

    /**
     * @return list<array{url: string, options: array<string, mixed>}>
     */
    private function connectionAttempts(string $httpsUrl): array
    {
        $httpUrl = (string) preg_replace('#^https:#i', 'http:', $httpsUrl);
        $host = (string) parse_url($httpsUrl, PHP_URL_HOST);
        $ipv4 = $host !== '' ? gethostbyname($host) : $host;

        $attempts = [
            ['url' => $httpsUrl, 'options' => []],
        ];

        if ($ipv4 !== '' && $ipv4 !== $host && filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $attempts[] = [
                'url' => $httpsUrl,
                'options' => [
                    'curl' => [
                        CURLOPT_RESOLVE => [
                            $host.':443:'.$ipv4,
                            $host.':80:'.$ipv4,
                        ],
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ],
                ],
            ];
        }

        if ($httpUrl !== $httpsUrl) {
            $attempts[] = ['url' => $httpUrl, 'options' => []];
        }

        return $attempts;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function client(string $token, array $options): PendingRequest
    {
        return Http::timeout(15)
            ->connectTimeout(8)
            ->withOptions($options)
            ->withHeaders(['X-Receipt-Token' => $token])
            ->acceptJson()
            ->withUserAgent('GGCAST-ReceiptPrinter/1.0');
    }
}
