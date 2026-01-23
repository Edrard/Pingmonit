<?php

namespace Edrard\Pingmonit\State;

use Edrard\Pingmonit\Contracts\StateStoreInterface;
use edrard\Log\MyLog;

class JsonStateStore implements StateStoreInterface
{
    private $stateFile;

    public function __construct($stateFile)
    {
        $this->stateFile = $stateFile;
    }

    public function load()
    {
        if (!is_string($this->stateFile) || $this->stateFile === '') {
            return [];
        }

        if (!file_exists($this->stateFile)) {
            return [];
        }

        $content = @file_get_contents($this->stateFile);
        if ($content === false) {
            return [];
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            MyLog::warning('Failed to decode system state JSON: ' . json_last_error_msg());
            return [];
        }

        return $decoded;
    }

    public function save(array $state)
    {
        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to encode state JSON: ' . json_last_error_msg());
        }

        file_put_contents($this->stateFile, $json);
    }
}
