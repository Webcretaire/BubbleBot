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
    public readonly string $twitchOathSecret;
    public readonly string $twitchNick;
    public readonly string $twitchChannel;
    public readonly string $twitchClientId;
    public readonly string $twitchClientSecret;
    public readonly string $twitchWebhookUrl;
    public readonly string $twitchChannelId;
    public readonly string $webDomain;
    public readonly string $overlayKey;

    function __construct()
    {
        (new Dotenv())->load(__DIR__ . '/../.env');

        $this->discordToken           = $_ENV['DISCORD_TOKEN'];
        $this->httpPort               = $_ENV['HTTP_PORT'];
        $this->githubSecret           = $_ENV['GITHUB_SECRET'];
        $this->botDisplayName         = $_ENV['BOT_DISPLAY_NAME'];
        $this->roleChannelName        = $_ENV['ROLE_CHANNEL_NAME'];
        $this->lsaNotificationChannel = $_ENV['LSA_NOTIFICATIONS_CHANNEL'];
        $this->twitchOathSecret       = $_ENV['TWITCH_OATH_SECRET'];
        $this->twitchNick             = $_ENV['TWITCH_NICK'];
        $this->twitchChannel          = $_ENV['TWITCH_CHANNEL'];
        $this->twitchChannelId        = $_ENV['TWITCH_CHANNEL_ID'];
        $this->twitchClientId         = $_ENV['TWITCH_CLIENT_ID'];
        $this->twitchClientSecret     = $_ENV['TWITCH_CLIENT_SECRET'];
        $this->twitchWebhookUrl       = $_ENV['TWITCH_WEBHOOK_URL'];
        $this->webDomain              = $_ENV['WEB_DOMAIN'];
        $this->overlayKey             = $_ENV['OVERLAY_KEY'];
    }
}
