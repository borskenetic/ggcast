<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Network receipt printer (ESC/POS over Ethernet)
    |--------------------------------------------------------------------------
    |
    | Leave RECEIPT_PRINTER_HOST empty if the printer is USB on this PC.
    |
    */
    'receipt_host' => env('RECEIPT_PRINTER_HOST', ''),
    'receipt_port' => (int) env('RECEIPT_PRINTER_PORT', 9100),
    'receipt_timeout' => (int) env('RECEIPT_PRINTER_TIMEOUT', 4),
    'receipt_width' => (int) env('RECEIPT_PRINTER_WIDTH', 48),

    /*
    |--------------------------------------------------------------------------
    | USB printer on a Windows PC
    |--------------------------------------------------------------------------
    |
    | Share the printer in Windows (Printer properties → Sharing) using this
    | share name. Checkout on this PC then prints over USB with no Ethernet
    | cable to the printer.
    |
    */
    'receipt_windows_name' => env('RECEIPT_PRINTER_NAME', 'POS-80'),

    /*
    |--------------------------------------------------------------------------
    | Optional print agent (tablet on Wi‑Fi → this PC)
    |--------------------------------------------------------------------------
    |
    | Run `php artisan receipt:agent` on the PC that has the USB printer.
    | Put that PC's LAN URL here so the tablet can send receipts to it.
    | Example: http://192.168.1.20:8099/print
    |
    */
    'agent_url' => env('RECEIPT_PRINT_AGENT_URL', ''),
    'agent_token' => env('RECEIPT_PRINT_AGENT_TOKEN', ''),
    'agent_host' => env('RECEIPT_PRINT_AGENT_HOST', '0.0.0.0'),
    'agent_port' => (int) env('RECEIPT_PRINT_AGENT_PORT', 8099),

    /*
    |--------------------------------------------------------------------------
    | Laptop watcher (tablet uses live HTTPS OPAC)
    |--------------------------------------------------------------------------
    |
    | On the laptop with the USB printer:
    |   RECEIPT_POLL_URL=https://ggcast.pantas.org
    |   php artisan receipt:watch
    |
    */
    'poll_url' => env('RECEIPT_POLL_URL', ''),

];
