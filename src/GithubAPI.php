<?php

namespace App;

use Discord\Builders\MessageBuilder;
use Discord\Http\Exceptions\NoPermissionsException;
use Discord\Parts\Channel\Channel;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\ExtendedPromiseInterface;

class GithubAPI
{
    const GITHUB_AUTH_HEADER = 'X-Hub-Signature-256';

    private Parameters $parameters;

    /** @var Channel[] */
    private array $lsaNotificationChannels;

    public function __construct(Parameters $parameters)
    {
        $this->parameters = $parameters;
    }

    public function setLsaNotificationChannel(Channel $channel): void
    {
        $this->lsaNotificationChannels[$channel->guild->id] = $channel;
    }

    public function verifySecret(ServerRequestInterface $request): int
    {
        $header = $request->getHeader(self::GITHUB_AUTH_HEADER);

        // No GitHub header, this is a bad request
        if (empty($header))
            return 401;

        // Authenticate GitHub using webhook's secret (hash body with secret to check header)
        $body = (string)$request->getBody();
        if (!hash_equals(current($header), 'sha256=' . hash_hmac('sha256', $body, $this->parameters->githubSecret)))
            return 403;

        return 0;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ExtendedPromiseInterface
     *
     * @throws NoPermissionsException
     */
    public function onRequest(ServerRequestInterface $request): ExtendedPromiseInterface
    {
        $verif = $this->verifySecret($request);

        if ($verif !== 0)
            return HttpServer::httpAsyncResponse($verif);

        // Process data from GitHub
        $run = json_decode((string)$request->getBody())->workflow_run;
        if (
            isset($run->name) && $run->name === "pages build and deployment"
            && $run->status === "completed"
            && $run->conclusion === "success"
        ) {
            $message = $run?->head_commit?->message;
            if ($message && str_starts_with($message, 'deploy: ')) {
                $head_commit = substr(explode(': ', $run->head_commit->message)[1], 0, 10);
                foreach ($this->lsaNotificationChannels as $channel) {
                    $role       = $channel->guild->roles->get('name', 'LSA ping');
                    $pingAndNew = $role ? "<@&{$role->id}> new" : "New";
                    $channel->sendMessage(MessageBuilder::new()->setContent(
                        "**New LiveSplitAnalyzer update**

$pingAndNew version of the website is deployed, corresponding to commit $head_commit: https://github.com/Webcretaire/LiveSplitAnalyzer/commit/$head_commit

See this version live here: https://webcretaire.github.io/LiveSplitAnalyzer"
                    ));
                }
            }
        }

        // Success
        return HttpServer::httpAsyncResponse();
    }
}