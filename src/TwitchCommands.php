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

        echo '~~~ Config data:', PHP_EOL;
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
        // Hardcoded commands
        if ($parts[0] == 'addcmd' && $isMod) {
            $this->addCommand($parts[1], join(' ', array_slice($parts, 2)));
            return "Command {$parts[1]} added shroomPog";
        }
        if ($parts[0] == 'delcmd' && $isMod) {
            $this->removeCommand($parts[1]);
            return "Command {$parts[1]} removed";
        }

        // User (mod) made commands
        $cmd = $this->configData['commands'][$parts[0]];

        if (!isset($cmd))
            return '';

        // Look for parameters to replace
        preg_match_all('/\$\{([\da-zA-Z]+)\}/', $cmd, $matches);

        $out = $cmd;
        // Iterate over each parameter to replace and update the final string
        foreach(array_unique($matches[0]) as $rawParameter) {
            $parameter = substr($rawParameter, 2, -1); // Remove "${" and "}"

            $substitute = '';
            if ($parameter == 'user') $substitute = $user;

            if ($position = intval($parameter))
                $substitute = $parts[$position];

            $out = str_replace($rawParameter, $substitute, $out);
        }

        return $out;
    }

    private function addCommand(string $name, string $val): void
    {
        $this->configData['commands'][$name] = $val;
        $this->updateConfigOnDisk();
    }

    private function removeCommand(string $name): void
    {
        unset($this->configData['commands'][$name]);
        $this->updateConfigOnDisk();
    }
}