<?php
// Source : https://www.gekkode.com/developpement/creer-un-serveur-websocket-en-php/
require 'vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ws\PushSocket;

require('config.php');

try {
    $server = IoServer::factory(
        new HttpServer(
            new WsServer(
                new PushSocket(GATEWAY_URL)
            )
        ),
        WEBSOCKET_PORT,
        '127.0.0.1',
    );

    $server->run();
} catch (\Exception $e) {
    $data = (new \DateTime())->format('d.m.Y') . ': Exception in ' . __FILE__ . ': ' . $e->getMessage() . ' ' . $e->getTraceAsString() . PHP_EOL;
    $fp = fopen(pathinfo(__FILE__)['dirname'] . '/log.txt', 'a');
    fwrite($fp, $data);
}
