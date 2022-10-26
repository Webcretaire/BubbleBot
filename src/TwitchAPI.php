<?php

namespace App;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\PromiseInterface;
use function React\Promise\all;

class TwitchAPI
{
    private Parameters $parameters;

    private LoopInterface $loop;

    private string $accessToken;

    private string $verificationSecret;

    private Browser $client;

    private TwitchIRC $twitchIRC;

    function __construct(LoopInterface $loop, Parameters $parameters, TwitchIRC $twitchIRC)
    {
        $this->parameters         = $parameters;
        $this->loop               = $loop;
        $this->twitchIRC          = $twitchIRC;
        $this->verificationSecret = $this->str_rand();
        $this->client             = new Browser();
    }

    private function str_rand(int $length = 64): string
    {
        try {
            return bin2hex(random_bytes(($length - ($length % 2)) / 2));
        } catch (\Exception $e) {
            return '___whatever___';
        }
    }

    public function authenticate(): PromiseInterface
    {
        return $this->client->post(
            'https://id.twitch.tv/oauth2/token',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'client_id'     => $this->parameters->twitchClientId,
                'client_secret' => $this->parameters->twitchClientSecret,
                'grant_type'    => 'client_credentials'
            ])
        )->then(function (ResponseInterface $response) {
            $data = json_decode((string)$response->getBody()); // {"access_token":"xxxx","expires_in":xxxx,"token_type":"bearer"}

            if (!isset($data->access_token) || !isset($data->expires_in)) {
                echo 'Wrong format of Twitch API authentication response:', PHP_EOL;
                var_dump($data);
                exit(1);
            }

            $this->accessToken = $data->access_token;
            $this->loop->addTimer($data->expires_in, fn() => $this->authenticate());
        }, function (\Exception $e) {
            echo 'Error: ' . $e->getMessage() . PHP_EOL;
            exit(2);
        });
    }

    public function verifySecret(ServerRequestInterface $request): int
    {
        $messageId        = current($request->getHeader('Twitch-Eventsub-Message-Id'));
        $messageTimestamp = current($request->getHeader('Twitch-Eventsub-Message-Timestamp'));

        if (!$messageTimestamp || !$messageId)
            return 401;

        $body = (string)$request->getBody();

        $twitchHmac = current($request->getHeader('Twitch-Eventsub-Message-Signature'));

        $raw = "{$messageId}{$messageTimestamp}{$body}";

        $ourHmac = hash_hmac('sha256', $raw, $this->verificationSecret);

        $ourHmac = "sha256={$ourHmac}";

        if (!hash_equals($twitchHmac, $ourHmac))
            return 403;

        return 0;
    }

    public function onRequest(ServerRequestInterface $request): ExtendedPromiseInterface
    {
        if (current($request->getHeader('Twitch-Eventsub-Message-Type')) == 'webhook_callback_verification')
            return HttpServer::httpAsyncResponse(200, json_decode((string)$request->getBody())->challenge);

        $verif = $this->verifySecret($request);

        if ($verif !== 0)
            return HttpServer::httpAsyncResponse($verif);

        $data = json_decode((string)$request->getBody());

        switch ($data?->subscription?->type) {
            case 'channel.update':
                $this->onChannelUpdate($data);
                break;
            case 'stream.online':
                $this->onStreamOnline($data);
                break;
            case 'stream.offline':
                $this->onStreamOffline($data);
                break;
        }

        return HttpServer::httpAsyncResponse();
    }

    public function setupWebhooks(): PromiseInterface
    {
        return $this->removeAllWebhooks()->then(fn() => $this->createWebhooks());
    }

    private function removeAllWebhooks(): PromiseInterface
    {
        // Get the list of existing webhooks
        return $this->client->get(
            "https://api.twitch.tv/helix/eventsub/subscriptions",
            [
                'Authorization' => "Bearer {$this->accessToken}",
                'Client-Id'     => $this->parameters->twitchClientId,
            ])
            ->then(function (ResponseInterface $response) {
                $ids = array_map(fn(\stdClass $data) => $data->id, json_decode((string)$response->getBody())->data);

                // Delete all the existing webhooks
                return all(
                    array_map(
                        function (string $id) {
                            echo "Delete old event handler $id", PHP_EOL;
                            return $this->client->delete(
                                "https://api.twitch.tv/helix/eventsub/subscriptions?id=$id",
                                [
                                    'Authorization' => "Bearer {$this->accessToken}",
                                    'Client-Id'     => $this->parameters->twitchClientId,
                                ]
                            );
                        },
                        $ids
                    )
                );
            });
    }

    public function createWebhooks(): PromiseInterface
    {
        return all(array_map(
            fn($event) => $this->client->post(
                'https://api.twitch.tv/helix/eventsub/subscriptions',
                [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Client-Id'     => $this->parameters->twitchClientId,
                    'Content-Type'  => 'application/json'
                ],
                json_encode([
                    "type"      => $event,
                    "version"   => "1",
                    "condition" => ["broadcaster_user_id" => $this->parameters->twitchChannelId],
                    "transport" => [
                        "method"   => "webhook",
                        "callback" => $this->parameters->twitchWebhookUrl,
                        "secret"   => $this->verificationSecret
                    ]
                ], JSON_UNESCAPED_SLASHES)
            ),
            ['channel.update', 'stream.online', 'stream.offline']
        ))->then(function (ResponseInterface $response) {
            echo 'Subscription to webhooks successful', PHP_EOL;
        }, function (\Exception $e) {
            echo 'Error: ' . $e->getMessage() . PHP_EOL;
            exit(2);
        });
    }

    private function onStreamOnline(\stdClass $data): void
    {
        if ($channel = $this->twitchIRC->getChannel($data->event->broadcaster_user_login)) {
            $channel->sendMessage("Hello everyone Burgy Did anyone snail me?");
            $channel->resetSeenUsers();
        }
        echo "Twitch channel goes live", PHP_EOL;
    }

    private function onStreamOffline(\stdClass $data): void
    {
        if ($channel = $this->twitchIRC->getChannel($data->event->broadcaster_user_login)) {
            $channel->sendMessage("Bye everyone, hope you enjoyed the stream celesteSquish");
            $channel->resetSeenUsers();
        }
        echo "Twitch channel ends stream", PHP_EOL;
    }

    private function onChannelUpdate(\stdClass $data): void
    {
        if ($channel = $this->twitchIRC->getChannel($data->event->broadcaster_user_login))
            $channel->sendMessage("Twitch channel update: new category = {$data->event->category_name} ; new title = {$data->event->title}");
        echo "Twitch channel update:", PHP_EOL;
        echo "    - New category is {$data->event->category_name}", PHP_EOL;
        echo "    - New title is {$data->event->title}", PHP_EOL;
    }
}