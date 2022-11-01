<?php

namespace App;

use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;

class TwitchIRC
{
    private LoopInterface $loop;

    private string $secret;

    private string $nick;

    /** @var TwitchChannel[] */
    private array $channels;

    private TwitchCommands $commands;

    private Parameters $parameters;

    private Connector $connector;

    public ?ConnectionInterface $connection = null;

    function __construct(LoopInterface $loop, Parameters $parameters)
    {
        $this->parameters = $parameters;

        $this->loop      = $loop;
        $this->secret    = $this->parameters->twitchOathSecret;
        $this->nick      = $this->parameters->twitchNick;
        $this->channels  = [];
        $this->connector = new Connector([], $this->loop);
        $this->commands  = new TwitchCommands();
    }

    public function setup(): void
    {
        if ($this->connection) return;

        $url  = 'irc.chat.twitch.tv';
        $port = '6667';

        $twitch = $this;
        $this->connector->connect("$url:$port")->then(
            function (ConnectionInterface $connection) use ($twitch) {
                $twitch->connection = $connection;
                $twitch->initIRC($connection);

                $connection->on('data', fn($data) => $twitch->process($data, $connection));
                $connection->on('close', fn() => exit(42));
            }
        );
    }

    public function teardown(): void
    {
        if (!$this->connection) return;

        foreach ($this->channels as $channel) $this->leaveChannel($channel);
    }

    public function joinChannel(string $chan): void
    {
        if (!$this->connection) return;

        $chan = strtolower($chan);
        echo "Join Twitch IRC channel $chan", PHP_EOL;
        $this->connection->write("JOIN #" . $chan . "\n");
        if (!isset($this->channels[$chan])) $this->channels[$chan] = new TwitchChannel($this, $this->commands, $chan);
    }

    public function leaveChannel(TwitchChannel $channel): void
    {
        if (!$this->connection) return;

        $this->connection->write("PART #{$channel->name}\n");
        unset ($this->channels[$channel->name]);
    }

    public function getChannel(string $chan): ?TwitchChannel
    {
        return $this->channels[$chan];
    }

    protected function initIRC(ConnectionInterface $connection): void
    {
        $connection->write("PASS " . $this->secret . "\n");
        $connection->write("NICK " . $this->nick . "\n");
        $connection->write("CAP REQ :twitch.tv/membership twitch.tv/commands twitch.tv/tags\n");
        $this->joinChannel($this->parameters->twitchChannel);
    }

    protected function pingPong(ConnectionInterface $connection): void
    {
        $connection->write("PONG :tmi.twitch.tv\n");
    }

    protected function process(string $data, ConnectionInterface $connection): void
    {
        if (trim($data) == "PING :tmi.twitch.tv") {
            $this->pingPong($connection);
            return;
        }
        if (str_contains($data, 'PRIVMSG')) {
            preg_match(
                "/^(.*):([\da-zA-Z_]+)![\da-zA-Z_]+@[\da-zA-Z_]+\.tmi\.twitch\.tv PRIVMSG #([\da-zA-Z_]+) :(.*)\n$/",
                $data,
                $matches
            );
            if (count($matches) < 5) {
                fprintf(STDERR, "Couldn't parse message:\n%s", $data);
                return;
            }

            $tagString = $matches[1];
            $user      = $matches[2];
            $channel   = $this->channels[$matches[3]];
            $message   = $matches[4];
            if (str_ends_with($message, "\n"))
                $message = substr($message, 0, -1);
            if (str_ends_with($message, "\r"))
                $message = substr($message, 0, -1);

            $tags = [];
            array_map(
                function ($pair) use (&$tags) {
                    $keyval           = explode('=', $pair);
                    $tags[$keyval[0]] = $keyval[1];
                },
                explode(';', $tagString)
            );
            $channel->processMessage($user, $message, $tags);
        }
    }
}
