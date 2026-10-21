<?php

echo "PHP ".PHP_VERSION.PHP_EOL;
echo 'curl     : '.(extension_loaded('curl') ? 'yes' : 'NO').PHP_EOL;
echo 'openssl  : '.(extension_loaded('openssl') ? 'yes' : 'NO').PHP_EOL;
echo 'url fopen: '.(ini_get('allow_url_fopen') ? 'yes' : 'NO').PHP_EOL;
echo PHP_EOL;

$urls = [
    'https://ggcast.pantas.org/up',
    'http://ggcast.pantas.org/up',
];

$context = stream_context_create([
    'http' => [
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

foreach ($urls as $url) {
    echo "Trying {$url} ... ";
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        $error = error_get_last();
        echo 'FAILED'.PHP_EOL;
        echo '  '.($error['message'] ?? 'unknown error').PHP_EOL;
    } else {
        echo 'OK ('.strlen($body).' bytes)'.PHP_EOL;
        echo '  '.substr(trim(strip_tags($body)), 0, 80).PHP_EOL;
    }
    echo PHP_EOL;
}

echo 'If Chrome can open https://ggcast.pantas.org but this says FAILED,'.PHP_EOL;
echo 'PHP on this PC is missing HTTPS support (enable openssl in php.ini).'.PHP_EOL;
