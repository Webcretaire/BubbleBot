<?php

namespace App;

use Psr\Http\Message\ServerRequestInterface;
use React\Promise\ExtendedPromiseInterface;

class HttpAPI
{
    private TwitchConfig $twitchConfig;

    public function __construct()
    {
        $this->twitchConfig = TwitchConfig::getInstance();
    }

    public function handle(ServerRequestInterface $request): ExtendedPromiseInterface
    {
        return match ($request->getUri()->getPath()) {
            "/api/commands" => $this->listCommands(),
            "/api/quotes" => $this->listQuotes(),
            default => HttpServer::httpAsyncResponse(404, "This endpoint doesn't exist."),
        };
    }

    private function listCommands(): ExtendedPromiseInterface
    {
        return HttpServer::jsonResponse($this->twitchConfig->all('commands'));
    }

    private function listQuotes(): ExtendedPromiseInterface
    {
        return HttpServer::jsonResponse($this->twitchConfig->all('quotes'));
    }
}