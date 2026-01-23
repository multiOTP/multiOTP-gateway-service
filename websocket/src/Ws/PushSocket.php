<?php

namespace Ws;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class PushSocket implements MessageComponentInterface
{
    protected $clients;
    private $gatewayUrl;

    public function __construct(string $gatewayUrl)
    {
        $this->gatewayUrl = $gatewayUrl;
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $conn->requestId = null;

        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $params = explode('§', $msg);
        switch ($params[0]) {
            case 'EHLO':  // FORMAT EHLO§request_id§mouid§serial§text§body
                if (count($params) < 5) {
                    $from->send('ko;0');
                    return;
                }

                // Store push request id
                $from->requestId = $params[1];

                // Send the request for notification to the gateway
                $url = $this->gatewayUrl . '/api/phone/notify';
                $data = [
                    'mosid' => $params[3],
                    'title' => (count($params) > 4 && !empty($params[4])) ? $params[4] : 'Approve sign-in ?',
                    'mouid' => $params[2],
                    'body' => (count($params) > 5 && !empty($params[5])) ? $params[5] : 'multiOTP',
                    'request_id' => $params[1]
                ];
                // use key 'http' even if you send the request to https://...
                $options = [
                    'http' => [
                        'header' => "Content-type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                        'method' => 'POST',
                        'content' => http_build_query($data),
                    ],
                ];
                $context = stream_context_create($options);
                $result = @file_get_contents($url, false, $context); //@ suppress warnings
                if ($result === false || strpos($http_response_header[0], '200') === false) {
                    /* Handle error */
                    echo "Error\r\n";
                    if (strpos($http_response_header[0], '402') !== false) {
                        $from->send('ko;NO_LICENCE');
                    } else {
                        $from->send('ko;0');
                    }
                    $from->close();
                    return;
                }
                break;

            case 'OTP':  // OTP§request_id§OTP_VALUE§mouid§serial
                if (count($params) != 5) {
                    $from->close();
                    return;
                }

                // Search for the correct request id
                foreach ($this->clients as $client) {
                    if ($from !== $client &&  $params[1] == $client->requestId) {
                        $client->send('ok;' . $params[2]);
                        $client->close();
                        $from->close();
                        break;
                    }
                }
                break;
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }
}
