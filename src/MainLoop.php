<?php

namespace App;

use Discord\Discord;
use Discord\Exceptions\IntentException;
use Discord\Parts\Channel\Channel;
use Discord\Parts\User\Activity;
use Discord\Parts\WebSockets\MessageReaction;
use Discord\Parts\Channel\Message;
use Discord\Parts\Guild\Guild;
use Discord\WebSockets\Event;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class MainLoop
{
    private Discord $discord;
    private Parameters $parameters;
    private RoleReactionManager $roleReactionManager;
    private HttpServer $httpServer;
    private TwitchIRC $twitchIRC;
    private TwitchAPI $twitchApi;
    private GithubAPI $githubApi;

    /**
     * @throws IntentException
     */
    public function __construct()
    {
        $this->parameters = new Parameters();

        $logger = new Logger('DiscordLogger');
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

        $this->discord = new Discord(['token' => $this->parameters->discordToken, 'logger' => $logger]);
        $loop          = $this->discord->getLoop();

        $this->roleReactionManager = new RoleReactionManager($this->parameters);
        $this->twitchIRC           = new TwitchIRC($loop, $this->parameters);
        $this->githubApi           = new GithubAPI($this->parameters);
        $this->twitchApi           = new TwitchAPI($loop, $this->parameters, $this->twitchIRC);
        $this->httpServer          = new HttpServer($loop, $this->parameters, $this->twitchApi, $this->githubApi);
    }

    /**
     * Main event loop
     */
    public function run(): never
    {
        $this->twitchIRC->setup();
        $this->twitchApi->authenticate()->then(fn() => $this->twitchApi->setupWebhooks());

        $this->discord->on(
            'ready',
            function (Discord $discord) {
                $discord->updatePresence(new Activity(
                    $discord,
                    ['type' => Activity::TYPE_PLAYING, 'name' => 'Eating pizza']
                ));

                echo PHP_EOL;
                echo "====================", PHP_EOL;
                echo "=   Bot is ready!  =", PHP_EOL;
                echo "====================", PHP_EOL;
                echo PHP_EOL;
            }
        );

        // Listen for messages
        $this->discord->on(Event::MESSAGE_CREATE, fn(Message $m, Discord $d) => $this->onMessageCreate($m, $d));

        // Setup when connecting to a server
        $this->discord->on(Event::GUILD_CREATE, fn(Guild $g, Discord $d) => $this->onGuildConnect($g, $d));

        $this->discord->on(Event::MESSAGE_REACTION_ADD,
            fn(MessageReaction $r, Discord $d) => $this->onReactionAdd($r, $d));
        $this->discord->on(Event::MESSAGE_REACTION_REMOVE,
            fn(MessageReaction $r, Discord $d) => $this->onReactionRemove($r, $d));

        // Discord handles the main event loop, which we reuse in all our components to do stuff asynchronously
        $this->discord->run();
    }

    /**
     * @throws \Exception
     */
    private function onReactionAdd(MessageReaction $reaction, Discord $discord): void
    {
        $reaction->channel->messages->fetch($reaction->message_id)
            ->done(function (Message $message) use (&$reaction) {
                if ($this->roleReactionManager->isReactionRoleMessage($message))
                    $this->roleReactionManager->addRoleFromReaction($reaction, $message);
            });
    }

    /**
     * @throws \Exception
     */
    private function onReactionRemove(MessageReaction $reaction, Discord $discord): void
    {
        $reaction->channel->messages->fetch($reaction->message_id)
            ->done(function (Message $message) use ($reaction) {
                if ($this->roleReactionManager->isReactionRoleMessage($message))
                    $this->roleReactionManager->removeRoleFromReaction($reaction, $message);
            });
    }

    private function onGuildConnect(Guild $guild, Discord $discord): void
    {
        echo "-- Connected to guild " . $guild->name . PHP_EOL;

        /** @var Channel $channel */
        foreach ($guild->channels as $channel) {
            if ($channel->name === $this->parameters->roleChannelName)
                $this->roleReactionManager->checkAndPostReactionMessage($channel);
            if ($channel->name === $this->parameters->lsaNotificationChannel)
                $this->githubApi->setLsaNotificationChannel($channel);
        }
    }

    /**
     * Callback to process a message asynchronously
     *
     * @param Message $message
     * @param Discord $discord
     */
    private function onMessageCreate(Message $message, Discord $discord): void
    {
        if ($this->roleReactionManager->isReactionRoleMessage($message))
            $this->roleReactionManager->setReactionRoleMessage($message->channel->guild, $message);
    }
}
