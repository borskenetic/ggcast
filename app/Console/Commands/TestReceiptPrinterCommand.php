<?php

namespace App\Console\Commands;

use App\Services\ReceiptPrinterService;
use Illuminate\Console\Command;

class TestReceiptPrinterCommand extends Command
{
    protected $signature = 'receipt:test';

    protected $description = 'Send a test page to the USB or Ethernet receipt printer';

    public function handle(ReceiptPrinterService $printer): int
    {
        if (! $printer->isConfigured()) {
            $this->error('No printer configured. For USB on this PC, share the printer as POS-80. For Ethernet, set RECEIPT_PRINTER_HOST.');

            return self::FAILURE;
        }

        $this->info('Sending test page to '.$printer->describeTarget().' …');

        if (! $printer->printTestPage()) {
            $this->error('Print failed. If this is USB: share the printer in Windows as POS-80, then try again.');

            return self::FAILURE;
        }

        $this->info('Test page sent.');

        return self::SUCCESS;
    }
}
