<?php

namespace App\Libraries;

use CodeIgniter\Exceptions\RuntimeException;
use CodeIgniter\View\View as BaseView;

/**
 * View class with additional functionality.
 * 
 * Adds syntax to provide default section contents in layouts
 * to be used if the section is not defined in the view.
 * 
 * @package App\Libraries
 */
class View extends BaseView
{
    protected $defaultSectionStack = [];

    public function renderSectionOrDefault(string $sectionName, bool $saveData = false): void
    {
        $this->defaultSectionStack[] = json_encode([
            'sectionName' => $sectionName,
            'saveData'    => $saveData,
        ]);

        ob_start();
    }

    public function endDefaultSection(): string
    {
        $defaultSectionContents = ob_get_clean();

        if ($this->defaultSectionStack === []) {
            throw new RuntimeException('View themes, no current section.');
        }

        $sectionConfig = json_decode(array_pop($this->defaultSectionStack));
        $sectionName   = $sectionConfig->sectionName;
        $saveData      = $sectionConfig->saveData;

        if (! isset($this->sections[$sectionName])) {
            return $defaultSectionContents;
        }

        $output = '';

        foreach ($this->sections[$sectionName] as $key => $contents) {
            $output .= $contents;
            if ($saveData === false) {
                unset($this->sections[$sectionName][$key]);
            }
        }

        return $output;
    }
}
