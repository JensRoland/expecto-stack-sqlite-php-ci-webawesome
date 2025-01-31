<?php
namespace App\Models\traits;

trait StripOmittableEmptyStrings
{
    protected function stripOmittableEmptyStrings(array $insertData)
    {
        foreach ($this->omittableFields as $field)
        {
            // If processing a batch of data, loop through each row
            if (array_is_list($insertData['data']))
            {
                foreach ($insertData['data'] as &$record)
                {
                    if (isset($record[$field]) && $record[$field] === '')
                    {
                        unset($record[$field]);
                    }
                }
            }
            // If processing a single row, convert empty strings to null
            else
            {
                if (isset($insertData['data'][$field]) && $insertData['data'][$field] === '')
                {
                    unset($insertData['data'][$field]);
                }
            }
        }
        return $insertData;
    }
}
