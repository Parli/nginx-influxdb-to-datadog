<?php
require 'vendor/autoload.php';

$host = getenv('HOST') ?: 'localhost';
$port = getenv('PORT') ?: 8089;

$statsdHost = getenv('STATSD_HOST') ?: 'localhost';
$statsdPort = getenv('STATSD_PORT') ?: '8125';

if (getenv('RESOLVE_STATSD_HOST')) {
    if ($dns = dns_get_record($statsdHost, \DNS_A)) {
        $statsdHost = $dns[0]['ip'];
    }
}

$dd = new DataDog\DogStatsd(['host' => $statsdHost, 'port' => $statsdPort]);
$writer = new Slant\Monitoring\Rewriter($dd);


$serverAddress = sprintf('%s:%d', $host, $port);
echo "Listening on $serverAddress\n";
echo "Rewriting to $statsdHost:$statsdPort\n";

$loop = React\EventLoop\Factory::create();
$factory = new React\Datagram\Factory($loop);

$factory->createServer($serverAddress)->then(function (React\Datagram\Socket $server)  use ($writer) {
    $server->on('message', function($message, $address, $server) use ($writer) {
        $writer->rewrite($message);
    });
});
$loop->run();
echo "Exiting.\n";
