<?php

namespace Edrard\Pingmonit;

use edrard\Log\MyLog;

class StateManager
{
    private $stateFile;
    private $state = [];

    public function __construct($stateFile)
    {
        $this->stateFile = $stateFile;
        $this->load();
    }

    public function load()
    {
        if (!is_string($this->stateFile) || $this->stateFile === '') {
            $this->state = [];
            return;
        }

        if (!file_exists($this->stateFile)) {
            $this->state = [];
            return;
        }

        $content = @file_get_contents($this->stateFile);
        if ($content === false) {
            $this->state = [];
            return;
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            MyLog::warning('Failed to decode system state JSON: ' . json_last_error_msg());
            $this->state = [];
            return;
        }

        $this->state = $decoded;
    }

    public function save()
    {
        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($this->state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to encode state JSON: ' . json_last_error_msg());
        }

        file_put_contents($this->stateFile, $json);
    }

    public function getHostState($ip)
    {
        return $this->state[$ip] ?? null;
    }

    public function setHostState($ip, array $data)
    {
        $this->state[$ip] = $data;
    }

    public function all()
    {
        return $this->state;
    }
}