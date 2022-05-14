<?php

namespace App;

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Message\Response;
use React\Http\HttpServer as ReactHttpServer;
use React\Promise\ExtendedPromiseInterface;
use React\Socket\SocketServer;
use function React\Promise\resolve;

class HttpServer
{
    const GITHUB_AUTH_HEADER = 'X-Hub-Signature-256';

    private Parameters $parameters;

    public function __construct(LoopInterface $loop)
    {
        $this->parameters = Parameters::getInstance();
        
        $server = new ReactHttpServer($loop, fn(ServerRequestInterface $r) => $this->onRequest($r));
        $socket = new SocketServer(sprintf('tcp://127.0.0.1:%d', $this->parameters->httpPort), [], $loop);
        $socket->on('error',
            function ($e) {
                fwrite(
                    STDERR,
                    sprintf("Error in socket server : %s%s", $e->getMessage(), PHP_EOL)
                );
                fwrite(
                    STDERR,
                    sprintf("Complete trace :%s%s", PHP_EOL, $e->getTraceAsString())
                );
            });
        $server->listen($socket);
    }

    /**
     * @param int    $code
     * @param string $body
     * @param string $contentType
     *
     * @return ExtendedPromiseInterface
     */
    public static function httpAsyncResponse(int $code = 200, string $body = '', string $contentType = 'text/plain'): ExtendedPromiseInterface
    {
        return resolve(new Response($code, ['Content-Type' => $contentType], $body));
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
            switch($request->getUri()->getPath()) {
                case "/github":
                    return $this->onGithubRequest($request);
                    break;
                default:
                    return self::httpAsyncResponse(404, "This endpoint doesn't exist.");
            }
        } catch (\Exception $e) {
            return $errorCallback($e);
        }
    }

    private function onGithubRequest(ServerRequestInterface $request): ExtendedPromiseInterface {
        $header = $request->getHeader(self::GITHUB_AUTH_HEADER);

        // No Github header, this is a bad request
        if (empty($header))
            return self::httpAsyncResponse(401);

        // Authenticate Github using webhook's secret (hash body with secret to check header)
        $body = (string)$request->getBody();
        if (current($header) !== 'sha256=' . hash_hmac('sha256', $body, $this->parameters->githubSecret))
            return self::httpAsyncResponse(403);

        // Process data from GitHub

        // Success
        return self::httpAsyncResponse();
    }
}
