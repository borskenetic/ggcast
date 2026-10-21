<?php

namespace App\Console\Commands;

use App\Services\ReceiptPrinterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

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
        if ($base === '' || str_contains($base, 'localhost') || str_contains($base, '127.0.0.1')) {
            $base = 'https://ggcast.pantas.org';
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
        $jobsUrl = $base.'/api/receipt-jobs/next';

        while (true) {
            try {
                $response = $this->liveHttp($token)->get($jobsUrl);

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
                        $this->liveHttp($token)->post($base.'/api/receipt-jobs/'.$id.'/printed');
                        $this->info(now()->format('H:i:s').' Printed job #'.$id);
                    } else {
                        $this->liveHttp($token)->post($base.'/api/receipt-jobs/'.$id.'/release');
                        $this->warn(now()->format('H:i:s').' Print failed for job #'.$id.'. Will retry.');
                    }
                } elseif (time() - $lastHeartbeat >= 30) {
                    $this->line(now()->format('H:i:s').' Waiting for checkouts…');
                    $lastHeartbeat = time();
                }
            } catch (\Throwable $e) {
                $this->warn(now()->format('H:i:s').' Network hiccup: '.$e->getMessage());
                $this->warn('Still trying '.$jobsUrl.' (IPv4). Check this PC is online.');
            }

            sleep($interval);
        }
    }

    private function liveHttp(string $token): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(15)
            ->connectTimeout(10)
            ->withOptions(['force_ip_resolve' => 'v4'])
            ->withHeaders(['X-Receipt-Token' => $token])
            ->acceptJson();
    }
}
