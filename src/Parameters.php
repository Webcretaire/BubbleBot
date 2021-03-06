<?php

namespace App;

use Symfony\Component\Dotenv\Dotenv;

class Parameters
{
    private static ?Parameters $instance = null;

    public readonly string $discordToken;
    public readonly string $httpPort;
    public readonly string $githubSecret;
    public readonly string $botDisplayName;
    public readonly string $roleChannelName;
    public readonly string $lsaNotificationChannel;

    private function __construct() {
        (new Dotenv())->load(__DIR__ . '/../.env');

        $this->discordToken = $_ENV['DISCORD_TOKEN'];
        $this->httpPort = $_ENV['HTTP_PORT'];
        $this->githubSecret = $_ENV['GITHUB_SECRET'];
        $this->botDisplayName = $_ENV['BOT_DISPLAY_NAME'];
        $this->roleChannelName = $_ENV['ROLE_CHANNEL_NAME'];
        $this->lsaNotificationChannel = $_ENV['LSA_NOTIFICATIONS_CHANNEL'];
    }

    public static function getInstance(): self
    {
        if (!self::$instance)
            self::$instance = new Parameters;

        return self::$instance;
    }
}
