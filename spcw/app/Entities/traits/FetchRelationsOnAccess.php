<?php

namespace App\Entities\traits;

trait FetchRelationsOnAccess
{
    // Relationships that should be loaded on access
    protected $withForeign = [];

    /**
     * Add relationships to load automatically on access.
     *
     * @param string ...$relations The relationships to load
     * 
     * @return self
     */
    public function with(string ...$relations): self
    {
        $this->withForeign = array_merge($this->withForeign, $relations);
        return $this;
    }

    /**
     * Fetch a foreign model entity by key.
     * 
     * Only fetches the foreign entity if it is in the withForeign list.
     * Otherwise, returns the foreign key as is.
     *
     * @param string $fieldName The field name containing the foreign key
     * @param string $modelName The model class to fetch
     * 
     * @return mixed The foreign entity or the foreign key
     */
    protected function fetchForeign(string $fieldName, string $modelName)
    {
        if (! isset($this->attributes[$fieldName])) {
            return null;
        }

        if (! in_array($fieldName, $this->withForeign)) {
            return $this->attributes[$fieldName];
        }

        return model($modelName)->find($this->attributes[$fieldName]);
    }
}
