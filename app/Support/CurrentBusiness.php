<?php

namespace App\Support;

use App\Models\Business;

class CurrentBusiness
{
    protected ?Business $business = null;

    public function set(Business $business): void
    {
        $this->business = $business;
    }

    public function get(): ?Business
    {
        return $this->business;
    }

    public function id(): ?int
    {
        return $this->business?->id;
    }

    public function clear(): void
    {
        $this->business = null;
    }
}

