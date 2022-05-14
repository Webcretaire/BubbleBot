<?php

namespace App;

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
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

    /** @var Channel[] */
    private array $lsaNotificationChannels;

    public function __construct(LoopInterface $loop)
    {
        $this->parameters = Parameters::getInstance();

        $server = new ReactHttpServer($loop, fn (ServerRequestInterface $r) => $this->onRequest($r));
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

    public function setLsaNotificationChannel(Channel $channel)
    {
        $this->lsaNotificationChannels[$channel->guild->id] = $channel;
    }

    private function onRequest(ServerRequestInterface $request): ExtendedPromiseInterface
    {
        $eol = PHP_EOL;

        $errorCallback = fn ($e) => self::httpAsyncResponse(
            500,
            sprintf(
                "Error while processing HTTP request : %s{$eol}Complete trace :{$eol}%s",
                $e->getMessage(),
                $e->getTraceAsString()
            )
        );

        try {
            switch ($request->getUri()->getPath()) {
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

    private function onGithubRequest(ServerRequestInterface $request): ExtendedPromiseInterface
    {
        $header = $request->getHeader(self::GITHUB_AUTH_HEADER);

        // No Github header, this is a bad request
        if (empty($header))
            return self::httpAsyncResponse(401);

        // Authenticate Github using webhook's secret (hash body with secret to check header)
        $body = (string)$request->getBody();
        if (current($header) !== 'sha256=' . hash_hmac('sha256', $body, $this->parameters->githubSecret))
            return self::httpAsyncResponse(403);

        // Process data from GitHub
        $run = json_decode($body)->workflow_run;
        if (
            $run->name === "pages build and deployment"
            && $run->status === "completed"
            && $run->conclusion === "success"
        ) {
            $head_commit = substr(explode(': ', $run->head_commit->message)[1], 0, 10);
            foreach ($this->lsaNotificationChannels as $channel) {
                $role = $channel->guild->roles->get('name', 'LSA ping');
                $pingAndNew = $role ? "<@&{$role->id}> new" : "New";
                $channel->sendMessage(MessageBuilder::new()->setContent(
                    "**New LiveSplitAnalyzer update**

$pingAndNew version of the website is deployed, corresponding to commit $head_commit: https://github.com/Webcretaire/LiveSplitAnalyzer/commit/$head_commit

See this version live here: https://webcretaire.github.io/LiveSplitAnalyzer"
                ));
            }
        }

        // Success
        return self::httpAsyncResponse();
    }
}
