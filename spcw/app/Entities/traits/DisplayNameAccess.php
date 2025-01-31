<?php

namespace App\Entities\traits;

trait DisplayNameAccess
{
    public function displayName(): string
    {
        return $this->{$this->displayNameField};
    }

    public function __toString(): string
    {
        return $this->displayName();
    }
}
