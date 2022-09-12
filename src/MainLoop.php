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

class MainLoop
{
    private Discord $discord;
    private Parameters $parameters;
    private RoleReactionManager $roleReactionManager;
    private HttpServer $httpServer;
    private Twitch $twitch;

    /**
     * @throws IntentException
     */
    public function __construct()
    {
        $this->parameters = Parameters::getInstance();
        $this->roleReactionManager = new RoleReactionManager;
        $this->discord = new Discord(['token' => $this->parameters->discordToken]);
        $this->httpServer = new HttpServer($this->discord->getLoop());
        $this->twitch = new Twitch($this->discord->getLoop());
    }

    /**
     * Main event loop
     */
    public function run(): never
    {
        $this->twitch->setup();

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
        $this->discord->on(Event::MESSAGE_CREATE, fn (Message $m, Discord $d) => $this->onMessageCreate($m, $d));

        // Setup when connecting to a server
        $this->discord->on(Event::GUILD_CREATE, fn (Guild $g, Discord $d) => $this->onGuildConnect($g, $d));

        $this->discord->on(Event::MESSAGE_REACTION_ADD, fn (MessageReaction $r, Discord $d) => $this->onReactionAdd($r, $d));
        $this->discord->on(Event::MESSAGE_REACTION_REMOVE, fn (MessageReaction $r, Discord $d) => $this->onReactionRemove($r, $d));

        $this->discord->run();
    }

    private function onReactionAdd(MessageReaction $reaction, Discord $discord): void
    {
        $reaction->channel->messages->fetch($reaction->message_id)
            ->done(function (Message $message) use (&$reaction) {
                if ($this->roleReactionManager->isReactionRoleMessage($message))
                    $this->roleReactionManager->addRoleFromReaction($reaction, $message);
            });
    }

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
                $this->httpServer->setLsaNotificationChannel($channel);
        }
    }

    /**
     * Callback to process a message asynchronously
     *
     * @param Message $message
     * @param Discord $discord
     */
    private function onMessageCreate(Message $message, Discord $discord)
    {
        if ($this->roleReactionManager->isReactionRoleMessage($message))
            $this->roleReactionManager->setReactionRoleMessage($message->channel->guild, $message);
    }
}
