<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReceiptPrintAgentCommand extends Command
{
    protected $signature = 'receipt:agent
                            {--host= : Bind address (default 0.0.0.0)}
                            {--port= : Port (default 8099)}';

    protected $description = 'Listen on the LAN so the Wi‑Fi tablet can print to the USB printer on this PC';

    public function handle(): int
    {
        $host = $this->option('host') ?: (string) config('printing.agent_host', '0.0.0.0');
        $port = (int) ($this->option('port') ?: config('printing.agent_port', 8099));
        $router = base_path('scripts/receipt-agent-router.php');

        if (! is_file($router)) {
            $this->error('Missing '.$router);

            return self::FAILURE;
        }

        $this->info('Receipt print agent listening on http://'.$host.':'.$port);
        $this->line('Keep this window open. On the tablet, OPAC will POST to /print.');
        $this->line('Health check: http://THIS-PC-LAN-IP:'.$port.'/health');
        $this->newLine();

        $php = PHP_BINARY;
        $command = escapeshellarg($php).' -S '.escapeshellarg($host.':'.$port).' '.escapeshellarg($router);
        passthru($command, $code);

        return $code === 0 ? self::SUCCESS : self::FAILURE;
    }
}
