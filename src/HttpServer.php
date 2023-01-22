<?php

namespace App;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\HttpServer as ReactHttpServer;
use React\Http\Message\Response;
use React\Promise\ExtendedPromiseInterface;
use React\Socket\SocketServer;
use function React\Promise\resolve;

class HttpServer
{
    private Parameters $parameters;

    private TwitchAPI $twitchAPI;

    private GithubAPI $githubAPI;

    private HttpAPI $httpAPI;

    /**
     * @param LoopInterface $loop
     * @param Parameters    $parameters
     * @param TwitchAPI     $twitchAPI
     * @param GithubAPI     $githubAPI
     */
    public function __construct(LoopInterface $loop, Parameters $parameters, TwitchAPI $twitchAPI, GithubAPI $githubAPI)
    {
        $this->parameters = $parameters;
        $this->twitchAPI  = $twitchAPI;
        $this->githubAPI  = $githubAPI;
        $this->httpAPI    = new HttpAPI();

        $server = new ReactHttpServer($loop, fn(ServerRequestInterface $r) => $this->onRequest($r));
        $socket = new SocketServer(sprintf('tcp://127.0.0.1:%d', $this->parameters->httpPort), [], $loop);
        $socket->on(
            'error',
            function ($e) {
                fwrite(
                    STDERR,
                    sprintf("Error in socket server : %s%s", $e->getMessage(), PHP_EOL)
                );
                fwrite(
                    STDERR,
                    sprintf("Complete trace :%s%s", PHP_EOL, $e->getTraceAsString())
                );
            }
        );
        $server->listen($socket);
    }

    /**
     * @param int $code
     * @param string $body
     * @param string $contentType
     *
     * @return ExtendedPromiseInterface
     */
    public static function httpAsyncResponse(int $code = 200, string $body = '', string $contentType = 'text/plain'): ExtendedPromiseInterface
    {
        return resolve(new Response($code,
            ['Content-Type' => $contentType, 'Access-Control-Allow-Origin ' => '*'],
            $body));
    }

    public static function jsonResponse($data): ExtendedPromiseInterface
    {
        return self::httpAsyncResponse(200, json_encode($data), 'application/json');
    }

    private function onRequest(ServerRequestInterface $request): ExtendedPromiseInterface
    {
        $eol = PHP_EOL;

        $errorCallback = fn($e) => self::httpAsyncResponse(
            500,
            sprintf(
                "Error while processing HTTP request : %s{$eol}Complete trace :{$eol}%s",
                $e->getMessage(),
                $e->getTraceAsString()
            )
        );

        try {
            if (str_starts_with($request->getUri()->getPath(), '/api'))
                return $this->httpAPI->handle($request);

            return match ($request->getUri()->getPath()) {
                "/github" => $this->githubAPI->onRequest($request),
                "/twitch" => $this->twitchAPI->onRequest($request),
                default => self::httpAsyncResponse(404, "This endpoint doesn't exist."),
            };
        } catch (\Exception $e) {
            return $errorCallback($e);
        }
    }
}
