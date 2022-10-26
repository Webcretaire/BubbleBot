<?php

namespace App;

use Discord\Parts\Channel\Channel;
use Discord\Parts\WebSockets\MessageReaction;
use Discord\Parts\Channel\Message;
use Discord\Parts\Guild\Guild;
use Discord\Helpers\Collection;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Guild\Emoji;
use Discord\Parts\Guild\Role;
use Discord\Parts\User\Member;

use function React\Promise\all;
use function React\Promise\resolve;

class RoleReactionManager
{
    /** @var Role[] */
    private array $roleMessages = [];

    private Parameters $parameters;

    const REACTION_ROLE_MESSAGE = "**Get roles by reacting to this message**

*Pronoun roles:*
- :regional_indicator_a: He/Him
- :regional_indicator_b: She/Her
- :regional_indicator_c: They/Them
- :regional_indicator_d: Fae/Faer
- :regional_indicator_e: Ask

*Get pinged for things:*
- :keyboard: New LiveSplitAnalyzer activity
- :tv: Streams on Twitch";

    public function __construct(Parameters $parameters)
    {
        $this->parameters = $parameters;
    }

    private function roleNameFromEmoji(Emoji $emoji): string
    {
        return match ($emoji->name) {
            '🇦' => 'He/Him',
            '🇧' => 'She/Her',
            '🇨' => 'They/Them',
            '🇩' => 'Fae/Faer',
            '🇪' => 'Ask pronouns',
            '📺' => 'Stream ping',
            '⌨️' => 'LSA ping',
            default => '',
        };
    }

    public function addRoleFromReaction(MessageReaction $reaction, Message $message): void
    {
        $roleName = $this->roleNameFromEmoji($reaction->emoji);

        if (!$roleName) return;

        $guild = $message->channel->guild;

        $existingRole = $guild->roles->get('name', $roleName);

        $pingable = str_contains($roleName, 'ping');

        // Create role if it doesn't exist already
        $rolePromise = $existingRole
            ? resolve($existingRole)
            : $guild->createRole(['name' => $roleName, 'mentionable' => $pingable]);

        $members = $message->channel->guild->members;

        // No cache because this lib is NOT able to maintain a coherent cache
        $memberPromise = $members->fetch($reaction->user_id, true);

        all([$rolePromise, $memberPromise])
            ->then(function (array $results) {
                /** @var Role $results */
                $role = $results[0];
                /** @var Member $member */
                $member = $results[1];

                // Don't add roles to bot
                if ($member->displayname === $this->parameters->botDisplayName) return;

                $member->addRole($role);
            });
    }

    public function removeRoleFromReaction(MessageReaction $reaction, Message $message): void
    {
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

    public function checkAndPostReactionMessage(Channel $channel): void
    {
        $channel->getMessageHistory(['limit' => 50])
            ->then(function (Collection $messages) use (&$channel) {
                /** @var Message $message */
                foreach ($messages as $message) {
                    if ($this->isReactionRoleMessage($message)) {
                        $this->roleMessages[$channel->guild->id] = $message;
                        if ($message->content != self::REACTION_ROLE_MESSAGE)
                            $message->edit(MessageBuilder::new()->setContent(self::REACTION_ROLE_MESSAGE));
                        return resolve($message);
                    }
                }

                if (!isset($this->roleMessages[$channel->guild->id]))
                    return $channel->sendMessage(MessageBuilder::new()->setContent(self::REACTION_ROLE_MESSAGE));

                return resolve();
            })
            ->then(function (?Message $message) {
                if (!$message) return; // No reaction message on this server

                foreach (['🇦', '🇧', '🇨', '🇩', '🇪', '📺', '⌨️'] as $emoji)
                    $message->react($emoji);
            });
    }

    /**
     * @param Guild   $guild
     * @param Message $message
     */
    public function setReactionRoleMessage(Guild $guild, Message $message)
    {
        $this->roleMessages[$guild->id] = $message;
    }

    public function isReactionRoleMessage(?Message $message): bool
    {
        if (!$message) return false;

        if (isset($this->roleMessages[$message->channel->guild->id]))
            return $message->id == $this->roleMessages[$message->channel->guild->id]->id;

        $author = $message->author;
        if (!$author) return false;

        return $author->displayname === $this->parameters->botDisplayName
            && $message->channel->name === $this->parameters->roleChannelName
            && str_contains($message->content, '**Get roles by reacting to this message**');
    }
}
