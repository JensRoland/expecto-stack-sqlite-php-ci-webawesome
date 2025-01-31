<?php

namespace App\Entities\traits;

trait DisplayNameAccess
{
    public function displayName(): ?string
    {
        if (empty($this->{$this->displayNameField})) {
            // TOOD: log a warning
            return null;
        }
        return $this->{$this->displayNameField};
    }

    public function __toString(): string
    {
        return $this->displayName() ?? '';
    }
}
