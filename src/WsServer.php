<?php

namespace App;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class WsServer implements MessageComponentInterface
{
    private \SplObjectStorage $clients;

    private Parameters $parameters;

    public function __construct(Parameters $parameters)
    {
        $this->clients    = new \SplObjectStorage;
        $this->parameters = $parameters;
    }

    public function onOpen(ConnectionInterface $conn)
    {
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        try {
            $data = json_decode($msg, true);
            if (isset($data['action'])
                && $data['action'] == 'auth'
                && isset($data['key'])
                && $data['key'] == $this->parameters->overlayKey) {
                $this->clients->attach($from);
            }
        } catch (\Exception $e) {
            echo 'Error processing websocket message :';
            echo $e->getMessage();
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        if ($this->clients->contains($conn))
            $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        $conn->close();
    }

    public function sendEvent($action = 'event', $data = [])
    {
        foreach ($this->clients as $client)
            $client->send(json_encode(['action' => $action, 'data' => $data]));
    }
}