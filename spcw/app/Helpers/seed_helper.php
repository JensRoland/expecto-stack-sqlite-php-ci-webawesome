<?php

use CodeIgniter\Model;

/**
 * Insert seed data into the database in batches.
 * 
 * Handles the case where the seed data is asymmetric,
 * i.e. not all records have the same properties.
 * 
 * @param [type] $model
 * @param [type] $seedData
 * @return int The total number of records inserted.
 */
function insertSeedBatch($model, $seedData) : int {
    $groupedData = [];
    foreach ($seedData as $seedRecord) {
        // Since insertBatch requires all records to have the same propertied,
        // we iterate over the records, group them into separate arrays (by their
        // included properties), and insert these arrays separately.
        $props = array_keys($seedRecord);
        sort($props);
        $groupKey = join("$", $props);
        $groupedData[$groupKey][] = $seedRecord;
    }

    $totalInserted = 0;
    foreach ($groupedData as $groupKey => $seedDataGroup) {
        $recordsInserted = $model->insertBatch($seedDataGroup);
        if ($recordsInserted) {
            $totalInserted += $recordsInserted;
        }
    }
    return $totalInserted;
}

/**
 * Populate foreign keys in seed data.
 * 
 * Assuming that the field in the seed data is a display name rather than a primary key,
 * this function will transform those display names into the corresponding primary keys.
 * 
 * @param Model $foreignModel The model referenced by the display names.
 * @param array $seedData The seed data to populate.
 * @param string $fieldName The name of the field in the seed data that contains the display names.
 * 
 * @return array The seed data with the foreign keys populated.
 */
function populateForeignKeys(Model $foreignModel, array $seedData, string $fieldName) : array {
    $primaryKey = $foreignModel->primaryKey;
    $primaryDisplayName = $foreignModel->primaryDisplayName;

    // Fetch all the foreign table records and map them by their display names
    // For seeding, there's really no need to optimize this
    $seriesData = $foreignModel->findAll();
    $seriesMap = [];
    foreach ($seriesData as $series) {
        $seriesMap[$series->$primaryDisplayName] = $series->$primaryKey;
    }

    // Transform the display names in the seed data to the primary keys
    foreach ($seedData as &$seed) {
        $seed[$fieldName] = $seriesMap[$seed[$fieldName]];
    }

    return $seedData;
}
