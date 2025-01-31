<?php

namespace App\Controllers\traits;

trait FetchRelated
{
    /**
     * Fetch all records from a foreign model as options for a dropdown.
     *
     * @param string $modelName The model class to fetch from
     * 
     * @return array An associative array of primary key => display name
     */
    protected static function fetchAllAsOptions(string $modelName): array
    {
        $relatedItems = model($modelName)->findAll();

        $result = array();
        foreach($relatedItems as $item) {
            $result[$item->primaryKey()] = $item->displayName();
        }

        return $result;
    }
}
