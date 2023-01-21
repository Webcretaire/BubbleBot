<?php

namespace App;

class TwitchQuoteManager
{
    private TwitchConfig $config;

    public function __construct(TwitchConfig $config)
    {
        $this->config = $config;
    }

    public function matchQuoteCommand(array $parts, bool $isMod): string
    {
        if (!count($parts)) {
            return $this->randomQuote();
        } else if ($parts[0] == 'add') {
            $quoteNumber = $this->addQuote(join(' ', array_slice($parts, 1)));
            return "Quote $quoteNumber added";
        } else if ($parts[0] == 'delete' && isset($parts[1]) && intval($parts[1]) && $isMod) {
            return $this->removeQuote($parts[1]);
        } else if (intval($parts[0])) {
            return $this->getQuote($parts[0]);
        } else {
            return "Didn't understand your quote command, sorry";
        }
    }

    private function nextQuoteID(): string
    {
        $quotes = $this->config->all('quotes');

        if (!count($quotes)) return "1";

        return intval(array_key_last($quotes)) + 1;
    }

    private function addQuote(string $sentence): string
    {
        $id = $this->nextQuoteID();
        $this->config->set('quotes', $id, $sentence);

        return $id;
    }

    private function randomQuote(): string
    {
        $quotes = $this->config->all('quotes');
        $id = array_rand($quotes);

        return "Quote n°$id: " . $quotes[$id];
    }

    private function removeQuote(string $id): string
    {
        if (!$this->config->has('quotes', $id)) return "Quote n°$id doesn't exist";

        $this->config->delete('quotes', $id);

        return "Deleted quote n°$id";
    }

    private function getQuote(string $id): string
    {
        if (!$this->config->has('quotes', $id)) return "Quote n°$id doesn't exist";

        return $this->config->get('quotes', $id);
    }
}