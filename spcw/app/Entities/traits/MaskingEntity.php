<?php

namespace App\Entities\traits;

trait MaskingEntity
{
    // Explicitly access secret fields
    public function getSecretField(string $key) {
        $dbColumn = $this->mapProperty($key);

        if (! array_key_exists($dbColumn, $this->attributes)) {
            return null;
        }

        $result = $this->attributes[$dbColumn];

        // Do we need to mutate this into a date?
        if (in_array($dbColumn, $this->dates, true)) {
            $result = $this->mutateDate($result);
        }
        // Or cast it as something?
        elseif ($this->_cast) {
            $result = $this->castAs($result, $dbColumn);
        }

        return $result;
    }
}
