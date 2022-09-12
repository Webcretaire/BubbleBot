<?php

namespace App;

use Symfony\Component\Yaml\Yaml;

class TwitchCommands
{
    const CONFIG_PATH = __DIR__ . "/../twitch_config.yml";

    private array $configData = [];

    public function __construct()
    {
        if (file_exists(self::CONFIG_PATH)) {
            $rawData = Yaml::parse(file_get_contents(self::CONFIG_PATH));
            if (isset($rawData)) $this->configData = $rawData;
        }

        if (!isset($this->configData['commands'])) {
            $this->configData['commands'] = ['hi' => 'Hello :)'];
            $this->updateConfigOnDisk();
        }
        if (!isset($this->configData['variables'])) {
            $this->configData['variables'] = [];
            $this->updateConfigOnDisk();
        }

        echo '~~~ Config data:';
        var_dump($this->configData);
    }

    private function updateConfigOnDisk(): void
    {
        file_put_contents(self::CONFIG_PATH, Yaml::dump($this->configData));
    }

    /**
     * @param string[] $parts
     * @param bool     $isMod
     * @param string   $user
     * @return string
     */
    public function matchCommand(array $parts, bool $isMod, string $user): string
    {
        $cmd = $this->configData['commands'][$parts[0]];

        if (!isset($cmd))
            return '';

        return join(
            ' ',
            array_map(
                function ($el) use ($parts, $user) {
                    if (!preg_match('/\$\{(.*)\}/', $el, $matches)) return $el;

                    $arg = trim($matches[1]);

                    if ($arg == 'user') return $user;

                    if ($position = intval($arg))
                        return $parts[$position];

                    return '';
                },
                explode(' ', $cmd)
            )
        );
    }

    private function addCommand(string $name, string $val) {
        $this->configData['commands'][$name] = $val;
        $this->updateConfigOnDisk();
    }

    private function removeCommand(string $name, string $val) {
        unset($this->configData['commands'][$name]);
        $this->updateConfigOnDisk();
    }
}