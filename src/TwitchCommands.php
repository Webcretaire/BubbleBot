<?php

namespace App;

class TwitchCommands
{
    private TwitchQuoteManager $quoteManager;

    private TwitchConfig $config;

    public function __construct()
    {
        $this->config       = TwitchConfig::getInstance();
        $this->quoteManager = new TwitchQuoteManager();
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

        if ($parts[0] == 'quote')
            return $this->quoteManager->matchQuoteCommand(array_slice($parts, 1), $isMod);

        // User (mod) made commands
        $cmd = $this->config->get('commands', $parts[0]);

        if (!isset($cmd))
            return '';

        // Look for parameters to replace
        preg_match_all('/\$\{([\d\s+a-zA-Z]+)\}/', $cmd, $matches);

        $out = $cmd;
        // Iterate over each parameter to replace and update the final string
        foreach (array_unique($matches[0]) as $rawParameter) {
            $parameter = trim(substr($rawParameter, 2, -1)); // Remove "${" and "}"

            $substitute = '';
            if ($parameter == 'user')
                $substitute = $user;
            else if ($parameter == '0')
                $substitute = join(' ', array_slice($parts, 1));
            else if ($position = intval($parameter)) // Parameter is a valid integer
                $substitute = $parts[$position] ?? '';
            else if (str_starts_with($parameter, '++'))
                $substitute = $this->incrementVariable(trim(substr($parameter, 2)));
            else
                $substitute = $this->config->get('variables', $parameter) ?? '';

            $out = str_replace($rawParameter, $substitute, $out);
        }

        return $out;
    }

    private function addCommand(string $name, string $val): void
    {
        $this->config->set('commands', $name, $val);
    }

    private function removeCommand(string $name): void
    {
        $this->config->delete('commands', $name);
    }

    private function incrementVariable(string $varName): int
    {
        $value = $this->config->get('variables', $varName) ?? 0;
        ++$value;
        $this->config->set('variables', $varName, $value);

        return $value;
    }
}