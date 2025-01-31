<?php

namespace App\Entities\traits;

trait PrimaryKeyAccess
{
    public function primaryKey(): ?string
    {
        if (empty($this->{$this->primaryKeyField})) {
            // TOOD: log an error
            return null;
        }
        return $this->{$this->primaryKeyField};
    }
}
