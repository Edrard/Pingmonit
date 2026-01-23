<?php

namespace Edrard\Pingmonit\Lock;

use Edrard\Pingmonit\Contracts\LockInterface;

class FlockLock implements LockInterface
{
    private $lockFile;
    private $handle;

    public function __construct($lockFile)
    {
        $this->lockFile = $lockFile;
    }

    public function acquire()
    {
        $dir = dirname($this->lockFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->handle = fopen($this->lockFile, 'c+');
        if ($this->handle === false) {
            throw new \RuntimeException("Unable to open lock file: {$this->lockFile}");
        }

        if (!flock($this->handle, LOCK_EX | LOCK_NB)) {
            return false;
        }

        ftruncate($this->handle, 0);
        fwrite($this->handle, (string) getmypid());
        fflush($this->handle);

        return true;
    }

    public function release()
    {
        if (!is_resource($this->handle)) {
            return;
        }

        @flock($this->handle, LOCK_UN);
        @fclose($this->handle);
        $this->handle = null;
    }
}
