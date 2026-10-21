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
        $token = (string) config('printing.agent_token', '');
        $interval = max(1, (int) $this->option('interval'));

        if ($token === '') {
            $this->error('Set RECEIPT_PRINT_AGENT_TOKEN in .env (same value as on the live site).');

            return self::FAILURE;
        }

        $this->info('Watching '.$base.' for checkout receipts.');
        $this->info('Printing to '.$printer->describeTarget());
        $this->line('Keep this window open. Check out a book on the tablet, then a slip should print here.');
        $this->newLine();

        while (true) {
            try {
                $response = Http::timeout(8)
                    ->withHeaders(['X-Receipt-Token' => $token])
                    ->acceptJson()
                    ->get($base.'/api/receipt-jobs/next');

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
                        Http::timeout(8)
                            ->withHeaders(['X-Receipt-Token' => $token])
                            ->acceptJson()
                            ->post($base.'/api/receipt-jobs/'.$id.'/printed');
                        $this->info('Printed job #'.$id);
                    } else {
                        Http::timeout(8)
                            ->withHeaders(['X-Receipt-Token' => $token])
                            ->acceptJson()
                            ->post($base.'/api/receipt-jobs/'.$id.'/release');
                        $this->warn('Print failed for job #'.$id.'. Will retry.');
                    }
                }
            } catch (\Throwable $e) {
                $this->warn('Poll failed: '.$e->getMessage());
            }

            sleep($interval);
        }
    }
}
