<?php
namespace App\Models\traits;

trait UuidGenerator
{
    protected function autoGenerateUuids(array $insertData)
    {
        helper('uuid');
        foreach ($this->uuidFields as $field)
        {
            // If inserting a batch of data, loop through each row
            // and generate a UUID for each row that doesn't already have one
            if (array_is_list($insertData['data']))
            {
                foreach ($insertData['data'] as &$record)
                {
                    if (! isset($record[$field]))
                    {
                        $record[$field] = generate_uuidv7();
                    }
                }
            }
            // If inserting a single row, generate a UUID if one isn't already set
            else
            {
                if (! isset($insertData['data'][$field]))
                {
                    $insertData['data'][$field] = generate_uuidv7();
                }
            }
        }
        return $insertData;
    }
}
