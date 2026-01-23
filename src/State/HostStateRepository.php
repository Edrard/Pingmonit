<?php

namespace Edrard\Pingmonit\State;

use Edrard\Pingmonit\Contracts\StateStoreInterface;

class HostStateRepository
{
    private $store;
    private $state;

    public function __construct(StateStoreInterface $store)
    {
        $this->store = $store;
        $this->state = $this->store->load();
    }

    public function save()
    {
        $this->store->save($this->state);
    }

    public function get($ip)
    {
        return $this->state[$ip] ?? null;
    }

    public function set($ip, array $data)
    {
        $this->state[$ip] = $data;
    }

    public function all()
    {
        return $this->state;
    }
}
