<?php

namespace App;

use Discord\Discord;
use Discord\Parts\Channel\Channel;
use Discord\Parts\User\Activity;
use Discord\Parts\WebSockets\MessageReaction;
use Discord\Parts\Channel\Message;
use Discord\Parts\Guild\Guild;
use Discord\WebSockets\Event;
use Discord\Helpers\Collection;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Guild\Emoji;
use Discord\Parts\Guild\Role;
use Discord\Parts\User\Member;

use function React\Promise\all;
use function React\Promise\resolve;

class MainLoop
{
    private Discord $discord;
    private Parameters $parameters;
    /** @var Role[] */
    private array $roleMessages = [];

    public function __construct()
    {
        $this->parameters = Parameters::getInstance();
        $this->discord = new Discord(['token' => $this->parameters->discordToken]);
        $this->httpServer = new HttpServer($this->discord->getLoop());
    }

    /**
     * Main event loop
     */
    public function run(): never
    {
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

    private function roleNameFromEmoji(Emoji $emoji): string
    {
        // These litterals in "case" are not regular letters, but that's what Discord is giving us 
        switch ($emoji->name) {
            case '🇦':
                return 'He/Him';
                break;
            case '🇧':
                return 'She/Her';
                break;
            case '🇨':
                return 'They/Them';
                break;
            case '🇩':
                return 'Fae/Faer';
                break;
            case '🇪':
                return 'Ask pronouns';
                break;
            default:
                return '';
        }
    }

    private function onReactionAdd(MessageReaction $reaction, Discord $discord): void
    {
        $reaction->channel->messages->fetch($reaction->message_id)
            ->done(function (Message $message) use (&$reaction) {
                if ($this->isReactionRoleMessage($message)) {
                    $roleName = $this->roleNameFromEmoji($reaction->emoji);

                    if (!$roleName) return;

                    $guild = $message->channel->guild;

                    $existingRole = $guild->roles->get('name', $roleName);

                    // Create role if it doesn't exist already
                    $rolePromise = $existingRole
                        ? resolve($existingRole)
                        : $guild->createRole(['name' => $roleName]);

                    $members = $message->channel->guild->members;

                    // No cache because this lib is NOT able to maintain a coherent cache
                    $memberPromise = $members->fetch($reaction->user_id, true);

                    all([$rolePromise, $memberPromise])
                        ->then(function (array $results) {
                            /** @var Role */
                            $role = $results[0];
                            /** @var Member */
                            $member = $results[1];

                            $member->addRole($role);
                        });
                }
            });
    }

    private function onReactionRemove(MessageReaction $reaction, Discord $discord): void
    {
        $reaction->channel->messages->fetch($reaction->message_id)
            ->done(function (Message $message) use ($reaction) {
                if ($this->isReactionRoleMessage($message)) {
                    $roleName = $this->roleNameFromEmoji($reaction->emoji);

                    if (!$roleName) return;

                    $guild = $message->channel->guild;

                    $role = $guild->roles->get('name', $roleName);

                    if (!$role) return;

                    $members = $message->channel->guild->members;

                    // No cache because this lib is NOT able to maintain a coherent cache
                    $members->fetch($reaction->user_id, true)
                        ->then(function (Member $member) use (&$role) {
                            $member->removeRole($role);
                        });
                }
            });
    }

    private function onGuildConnect(Guild $guild, Discord $discord): void
    {
        echo "-- Connected to guild " . $guild->name . PHP_EOL;

        // Check if reaction role message exists, if not then send it

        /** @var Channel $channel */
        foreach ($guild->channels as $channel) {
            echo "  -> Found channel " . $channel->name . PHP_EOL;
            if ($channel->name === $this->parameters->roleChannelName) {
                $channel->getMessageHistory(['limit' => 50])
                    ->done(function (Collection $messages) use (&$channel) {
                        /** @var Message $message */
                        foreach ($messages as $message)
                            if ($this->isReactionRoleMessage($message))
                                $this->roleMessages[$channel->guild->id] = $message;

                        if (!isset($this->roleMessages[$channel->guild->id])) {
                            $channel->sendMessage(MessageBuilder::new()->setContent(
                                "**Get roles by reacting to this message**

*Pronoun roles:*
- :regional_indicator_a: He/Him
- :regional_indicator_b: She/Her
- :regional_indicator_c: They/Them
- :regional_indicator_d: Fae/Faer
- :regional_indicator_e: Ask"
                            ));
                        }
                    });
            }
        }
    }

    private function isReactionRoleMessage(?Message $message): bool
    {
        if (!$message) return false;

        if (isset($this->roleMessages[$message->channel->guild->id])) 
            return $message->id == $this->roleMessages[$message->channel->guild->id]->id;

        $author = $message->author;
        if (!$author) return false;

        return $author->displayname === $this->parameters->botDisplayName
            && $message->channel->name === $this->parameters->roleChannelName
            && strpos($message->content, '**Get roles by reacting to this message**') !== false;
    }

    /**
     * Callback to process a message asynchronously
     *
     * @param Message $message
     * @param Discord $discord
     */
    private function onMessageCreate(Message $message, Discord $discord)
    {
        if ($this->isReactionRoleMessage($message))
            $this->roleMessages[$message->channel->guild->id] = $message;
    }
}
