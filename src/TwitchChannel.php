<?php

namespace App;

class TwitchChannel
{
    private TwitchIRC $twitchIRC;

    private TwitchCommands $commands;

    public readonly string $name;

    private array $seenUsers = [];

    function __construct(TwitchIRC $twitchIRC, TwitchCommands $commands, string $name)
    {
        $this->twitchIRC = $twitchIRC;
        $this->commands  = $commands;
        $this->name      = $name;
    }

    public function sendMessage(string $data): void
    {
        $this->twitchIRC->connection->write("PRIVMSG #$this->name :$data\n");
    }

    public function isMod(string $user, array $tags = []): bool
    {
        return trim(strtolower($this->name)) == trim(strtolower($user)) || isset($tags['mod']) && $tags['mod'] == 1;
    }

    public function processMessage(string $user, string $message, array $tags): void
    {
        $response = '';
        if (!isset($this->seenUsers[$user])) {
            $this->sendMessage("Hello $user, welcome to today's stream peepoArrive");
            $this->seenUsers[$user] = true;
        }

        if (str_starts_with($message, '!'))
            $response = $this->commands->matchCommand(
                explode(' ', substr($message, 1)), // Remove ! before exploding message words
                $this->isMod($user, $tags),
                $user
            );
        if ($response)
            $this->sendMessage("$response");
    }

    public function resetSeenUsers(): void
    {
        $this->seenUsers = [];
    }
}