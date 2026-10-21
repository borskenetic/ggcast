<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ReceiptPrinterService
{
    public function isConfigured(): bool
    {
        return $this->networkHost() !== '' || $this->windowsPrinterName() !== '';
    }

    public function describeTarget(): string
    {
        if ($this->networkHost() !== '') {
            return $this->networkHost().':'.config('printing.receipt_port', 9100);
        }

        if ($this->windowsPrinterName() !== '') {
            return 'USB / Windows printer "'.$this->windowsPrinterName().'"';
        }

        return '(not configured)';
    }

    /**
     * @param  array{name?: string, id_number?: string}  $student
     * @param  list<array{title?: string, author?: string, barcode?: string, due_date?: string}>  $books
     */
    public function printCheckout(array $student, array $books): bool
    {
        if (! $this->isConfigured() || $books === []) {
            return false;
        }

        return $this->send($this->buildCheckoutReceipt($student, $books));
    }

    public function printTestPage(): bool
    {
        $library = (string) config('branding.library_name', 'Library');
        $now = now('Asia/Manila')->format('M j, Y g:i A');
        $payload = $this->init()
            .$this->center($library)
            .$this->center('Printer test')
            .$this->center($now)
            .$this->rule()
            .$this->line('If you can read this, receipt printing is working.')
            .$this->feed(4)
            .$this->cut();

        return $this->send($payload);
    }

    /**
     * @param  array{name?: string, id_number?: string}  $student
     * @param  list<array{title?: string, author?: string, barcode?: string, due_date?: string}>  $books
     */
    private function buildCheckoutReceipt(array $student, array $books): string
    {
        $library = (string) config('branding.library_name', 'Library');
        $now = now('Asia/Manila')->format('M j, Y g:i A');

        $out = $this->init()
            .$this->center($library)
            .$this->center($now)
            .$this->feed(1)
            .$this->line(strtoupper((string) ($student['name'] ?? '')))
            .$this->line((string) ($student['id_number'] ?? ''))
            .$this->rule()
            .$this->line("Today's Checkouts")
            .$this->rule();

        foreach ($books as $book) {
            $out .= $this->line((string) ($book['title'] ?? ''));
            if (! empty($book['author'])) {
                $out .= $this->line((string) $book['author']);
            }
            if (! empty($book['barcode'])) {
                $out .= $this->line('Barcode: '.$book['barcode']);
            }
            if (! empty($book['due_date'])) {
                $out .= $this->line('Due: '.$book['due_date']);
            }
            $out .= $this->rule();
        }

        $out .= $this->line('Served By: ________________')
            .$this->feed(4)
            .$this->cut();

        return $out;
    }

    private function send(string $payload): bool
    {
        if ($this->networkHost() !== '' && $this->sendOverNetwork($payload)) {
            return true;
        }

        if ($this->windowsPrinterName() !== '') {
            return $this->sendToWindowsPrinter($payload);
        }

        return false;
    }

    private function sendOverNetwork(string $payload): bool
    {
        $host = $this->networkHost();
        $port = (int) config('printing.receipt_port', 9100);
        $timeout = (int) config('printing.receipt_timeout', 4);

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($socket === false) {
            Log::warning('Receipt printer unreachable.', [
                'host' => $host,
                'port' => $port,
                'error' => $errstr,
                'code' => $errno,
            ]);

            return false;
        }

        stream_set_timeout($socket, $timeout);

        $written = fwrite($socket, $payload);
        fclose($socket);

        if ($written === false || $written === 0) {
            Log::warning('Receipt printer write failed.', [
                'host' => $host,
                'port' => $port,
            ]);

            return false;
        }

        return true;
    }

    private function sendToWindowsPrinter(string $payload): bool
    {
        $name = $this->windowsPrinterName();
        $share = '\\\\localhost\\'.$name;
        $tmp = tempnam(sys_get_temp_dir(), 'rcp');

        if ($tmp === false) {
            return false;
        }

        $bin = $tmp.'.bin';
        @unlink($tmp);

        if (file_put_contents($bin, $payload) === false) {
            return false;
        }

        $written = false;
        $handle = @fopen($share, 'wb');
        if (is_resource($handle)) {
            $bytes = fwrite($handle, $payload);
            fclose($handle);
            $written = $bytes !== false && $bytes > 0;
        }

        if (! $written) {
            $command = 'copy /B '.escapeshellarg($bin).' '.escapeshellarg($share);
            $output = [];
            $code = 1;
            exec($command.' 2>&1', $output, $code);
            $written = $code === 0;
            if (! $written) {
                Log::warning('Windows receipt printer write failed.', [
                    'printer' => $name,
                    'share' => $share,
                    'output' => implode("\n", $output),
                    'code' => $code,
                ]);
            }
        }

        @unlink($bin);

        return $written;
    }

    private function networkHost(): string
    {
        return trim((string) config('printing.receipt_host', ''));
    }

    private function windowsPrinterName(): string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return '';
        }

        return trim((string) config('printing.receipt_windows_name', ''));
    }

    private function init(): string
    {
        return "\x1B\x40";
    }

    private function cut(): string
    {
        return "\x1D\x56\x41\x03";
    }

    private function feed(int $lines = 1): string
    {
        return str_repeat("\n", max(1, $lines));
    }

    private function rule(): string
    {
        return str_repeat('-', $this->width())."\n";
    }

    private function center(string $text): string
    {
        $text = $this->ascii($text);
        $width = $this->width();
        $pad = max(0, (int) floor(($width - mb_strlen($text)) / 2));

        return str_repeat(' ', $pad).$text."\n";
    }

    private function line(string $text): string
    {
        $wrapped = wordwrap($this->ascii($text), $this->width(), "\n", true);

        return $wrapped."\n";
    }

    private function width(): int
    {
        $width = (int) config('printing.receipt_width', 48);

        return $width > 16 ? $width : 48;
    }

    private function ascii(string $text): string
    {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return $converted === false ? $text : $converted;
    }
}
