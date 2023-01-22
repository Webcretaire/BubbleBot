<?php

namespace App;

use Symfony\Component\Yaml\Yaml;

class TwitchConfig
{
    const CONFIG_PATH = __DIR__ . "/../twitch_config.yml";

    private array $configData = [];

    private static ?TwitchConfig $instance = null;

    public static function getInstance(): TwitchConfig
    {
        if (!self::$instance) self::$instance = new TwitchConfig();

        return self::$instance;
    }

    private function __construct()
    {
        if (file_exists(self::CONFIG_PATH)) {
            $rawData = Yaml::parse(file_get_contents(self::CONFIG_PATH));
            if (isset($rawData)) $this->configData = $rawData;
        }

        $this->initializeCategory('commands', ['hi' => 'Hello :)']);
        $this->initializeCategory('variables');
        $this->initializeCategory('quotes');

        echo '~~~ Config data:', PHP_EOL;
        var_dump($this->configData);
    }

    public function updateConfigOnDisk(): void
    {
        file_put_contents(self::CONFIG_PATH, Yaml::dump($this->configData));
    }

    public function has(string $category, string $entry): bool
    {
        return isset($this->configData[$category][$entry]);
    }

    public function get(string $category, string $entry): ?string
    {
        return $this->configData[$category][$entry] ?? null;
    }

    public function all(string $category): array
    {
        return $this->configData[$category] ?? [];
    }

    public function count(string $category): int
    {
        if (!isset($this->configData[$category])) return 0;

        return count($this->configData[$category]);
    }

    public function set(string $category, string $entry, string $value): void
    {
        if (!isset($this->configData[$category])) return;

        $this->configData[$category][$entry] = $value;
        $this->updateConfigOnDisk();
    }

    public function delete(string $category, string $entry): void
    {
        unset($this->configData[$category][$entry]);
        $this->updateConfigOnDisk();
    }

    private function initializeCategory(string $category, array $defaultValue = []): void
    {
        if (!isset($this->configData[$category])) {
            $this->configData[$category] = $defaultValue;
            $this->updateConfigOnDisk();
        }
    }
}