<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CLI\GeneratorTrait;

use App\Libraries\ExpectoEntityField;
use App\Libraries\ExpectoFieldType;
use App\Libraries\ExpectoFieldFormat;

class MakeCrud extends BaseCommand
{
    use GeneratorTrait;

    protected $group       = 'Generators';
    protected $name        = 'make:crud';
    protected $description = 'Generates CRUD Controllers, Models, Migrations, etc., for an Expecto Spec JSON.';
    protected $usage       = 'make:crud <file> [options]';
    protected $arguments   = [
        'file' => 'Path to a JSON file containing the application spec.',
    ];
    protected $options = [
        '--entity'    => 'Generate Entity classes as well',
        '--migration' => 'Generate Migration files as well',
        '--seeder'    => 'Generate Seeder files as well',
        '--force'     => 'Force overwrite existing files',
        '--dates'     => 'Toggle timestamps and soft-deletes on the models and entities.'
    ];
    // TODO: Add --soft-delete option

    public function run(array $params)
    {
        // Load helpers
        helper('inflector');

        // Validate spec file
        $fileName = array_shift($params);
        if (empty($fileName)) {
            CLI::error('You must provide a spec file.');
            CLI::newLine();
            return;
        }

        $spec = $this->loadSpec($fileName);
        $appName = $spec['appName'] ?? 'App';

        // First pass: Build a symbol table [NameOfEntityOrEnum => spec]
        $symbolTable = [];
        foreach($spec['dataEntities'] as $entitySpec) {
            $symbolTable[$entitySpec['name']] = $entitySpec;
        }

        // Second pass: Generate the CRUD scaffolding
        $menuEntities = [];
        foreach($spec['dataEntities'] as $entitySpec) {
            $entityNames = $this->parseEntityName($entitySpec['name']);

            // TODO: Handle `type:Enum` entities
            if ($entitySpec['type'] == 'Entity') {
                CLI::write('Generating CRUD scaffolding for ' . $entitySpec['name'] . '...');
                $this->generateAppEntity($entitySpec['name'], $entitySpec, $symbolTable);
                $menuEntities[] = [
                    'title' => $entityNames['humanPlural'],
                    'url'   => '/' . $entityNames['slug']
                ];
            }
        }

        // Generate the NavigationCell
        $this->generateNavigationCell($menuEntities);

        CLI::write("$appName generated successfully.", 'green');
    }

    protected function loadSpec($fileName) {
        $paths = config('Paths');
        $qualifiedFileName = dirname(realpath($paths->appDirectory)) . DIRECTORY_SEPARATOR . $fileName;
        if (! file_exists($qualifiedFileName)) {
            CLI::error('Spec file not found: ' . $qualifiedFileName);
            CLI::newLine();
            return;
        }

        $jsonData = json_decode(file_get_contents($qualifiedFileName), true);
        if (! $jsonData) {
            CLI::error('Spec file is not valid JSON: ' . $qualifiedFileName);
            CLI::newLine();
            return;
        }

        return $jsonData;
    }

    protected function generateAppEntity(string $entityName, array $entitySpec, array $symbolTable) {
        $entityNames = $this->parseEntityName($entityName);

        // Fetch options & parse fields
        $wantMigration = CLI::getOption('migration');
        $wantSeeder    = CLI::getOption('seeder');
        $wantEntity    = CLI::getOption('entity');
        $useDates      = CLI::getOption('dates') !== null;  // Flag: true if --dates is present
        $fieldSpecs    = $entitySpec['fields'] ?? [];

        // Map field specs to ExpectoEntityField objects
        $compactProjection = $entitySpec['compact'] ?? [];
        $fields = [];
        $relations = [];
        foreach ($fieldSpecs as $fieldName => $props) {

            // Check the symbol table in case this is a foreign key,
            // and replace the field type with the primary key type of the related entity.
            // TODO: handle the case where the type is defined as an array as in `addresses: PostalAddress[]`
            $fieldType = $props['type'];
            if (array_key_exists($fieldType, $symbolTable))
            {
                $foreignEntitySpec = $symbolTable[$fieldType];
                list($foreignKeyType, $foreignFieldName) = $this->getPrimaryKeyField($foreignEntitySpec['fields']);
                $props['type'] = $foreignKeyType;
                $props['relatedClassName'] = $fieldType;
                $relations[$fieldName] = $foreignEntitySpec['name'];
            }

            $fields[$fieldName] = new ExpectoEntityField($fieldName, $props);
            if (in_array($fieldName, $compactProjection)) {
                $fields[$fieldName]->inProjections[] = 'compact';
            }
        }

        // Generate Model, Controller, Migration, Seeder, Entity, etc.
        $this->generateModel($entityNames, $useDates, $fields, $wantEntity);
        $this->generateController($entityNames, $relations);

        if ($wantMigration) {
            $this->generateMigration($entityNames, $useDates, $fields);
        }
        if ($wantSeeder) {
            $seeds = $entitySpec['seeds'] ?? [];
            $this->generateSeeder($entityNames, $relations, $seeds);
        }
        if ($wantEntity) {
            $this->generateEntity($entityNames, $useDates, $fields);
        }

        // Generate views (new code for placeholders)
        $this->generateViews($entityNames, $fields);
    }

    /**
     * Generate the Model class.
     */
    protected function generateModel(array $entityNames, bool $useDates, array $fields, bool $wantEntity)
    {
        // Where the final file goes:
        $path = APPPATH . "Models/{$entityNames['class']}Model.php";

        // Load the stub
        $stub = file_get_contents(__DIR__ . '/Stubs/Model.stub');

        // Build up dynamic pieces
        $allowedFields = $this->prepareAllowedFields($fields);  // "['title','slug',...]"

        list($primaryKeyType, $primaryKeyField) = $this->getPrimaryKeyField($fields);
        $useDefaultPrimaryKey = $primaryKeyType == 'int' && $primaryKeyField == 'id';

        $validationRules = $this->prepareValidationRules($fields);
        if ($useDefaultPrimaryKey) {
            // Prepend the id field to the validation rules
            $validationRules = "'id' => 'permit_empty|integer|max_length[19]',\n        " . $validationRules;
        }

        // Timestamps
        $useTimestamps = $useDates ? 'true' : 'false';
        $createdField  = $useDates ? 'created_at' : '';
        $updatedField  = $useDates ? 'updated_at' : '';
        $deletedField  = $useDates ? 'deleted_at' : '';
        $useSoftDeletes= $useDates ? 'true' : 'false';

        // Entity
        $modelReturnType = $wantEntity
            ? "\\App\\Entities\\{$entityNames['class']}Entity::class"
            : "'array'";

        // Dependencies and traits
        $dependencies = ['App\Models\traits\StripOmittableEmptyStrings'];
        $traits = ['StripOmittableEmptyStrings'];

        // Callbacks
        $callbacks = [
            'beforeInsert' => ['stripOmittableEmptyStrings'],
            'beforeUpdate' => ['stripOmittableEmptyStrings'],
            'beforeInsertBatch' => ['stripOmittableEmptyStrings'],
            'beforeUpdateBatch' => ['stripOmittableEmptyStrings']
        ];
        $callbackExtras = [];
        // If the entity contains any UUID fields:
        // - add the UuidGenerator trait
        // - set the $uuidFields property
        // - add the $beforeInsert/$beforeInsertBatch callbacks
        $uuidFields = array_filter($fields, fn($expectoEntityField) => $expectoEntityField->format == ExpectoFieldFormat::UUID);
        if (! empty($uuidFields))
        {
            $dependencies[] = 'App\Models\traits\UuidGenerator';

            $traits[] = 'UuidGenerator';

            $callbacks['beforeInsert'][] = 'autoGenerateUuids';
            $callbacks['beforeInsertBatch'][] = 'autoGenerateUuids';

            $callbackExtras[] = '// Generate UUIDs on insert in these fields';
            $uuidFieldsArrayString = implode("', '", array_keys($uuidFields));
            $callbackExtras[] = "protected \$uuidFields = ['$uuidFieldsArrayString'];";
        }

        $callbacksStrings = [];
        foreach ($callbacks as $callbackName => $callbackMethods) {
            $callbackMethodsString = "['" . implode("', '", $callbackMethods) . "']";
            $callbacksStrings[] = "protected \${$callbackName} = {$callbackMethodsString};";
        }
        $callbacksString = implode("\n    ", $callbacksStrings);
        $callbackExtrasString = implode("\n    ", $callbackExtras);

        $omittableFieldsString = $this->prepareOmittableFields($fields);

        $traitsString = $traits ? 'use ' . implode(", ", $traits) . ";" : '';
        $dependenciesString = $dependencies ? 'use ' . implode(";\nuse ", $dependencies) . ";" : '';

        $primaryDisplayName = $this->getPrimaryDisplayName($fields);

        // Perform replacements
        $stub = str_replace([
            '{{className}}',
            '{{tablePlural}}',
            '{{primaryKeyField}}',
            '{{useAutoIncrement}}',
            '{{modelReturnType}}',
            '{{useSoftDeletes}}',
            '{{allowedFields}}',
            '{{useTimestamps}}',
            '{{createdField}}',
            '{{updatedField}}',
            '{{deletedField}}',
            '{{validationRules}}',
            '{{callbacks}}',
            '{{callbackExtras}}',
            '{{traits}}',
            '{{dependencies}}',
            '{{omittableFields}}',
            '{{primaryDisplayName}}'
        ], [
            $entityNames['class'],
            $entityNames['table'],
            $primaryKeyField,
            $useDefaultPrimaryKey ? 'true' : 'false',
            $modelReturnType,
            $useSoftDeletes,
            $allowedFields,
            $useTimestamps,
            $createdField,
            $updatedField,
            $deletedField,
            $validationRules,
            $callbacksString,
            $callbackExtrasString,
            $traitsString,
            $dependenciesString,
            $omittableFieldsString,
            $primaryDisplayName ? "'$primaryDisplayName'" : 'null'
        ], $stub);

        // Write file
        $this->writeFile($path, $stub, 'Model');
    }

    /**
     * Generate the Controller class.
     */
    protected function generateController(array $entityNames, array $relations)
    {
        $controllerName = $entityNames['class'];  // e.g. "User"
        $path           = APPPATH . "Controllers/{$controllerName}.php";

        $stub = file_get_contents(__DIR__ . '/Stubs/Controller.stub');

        // Build the eager fetch string
        // For the 'show' method, we eager fetch all relations (1 level deep only)
        $withEagerFetchOnShow = '';
        $fetchRelatedOptions = [];
        if (! empty($relations)) {
            $relationFieldNames = array_keys($relations);
            $withEagerFetchOnShow .= "->with('" . implode("', '", $relationFieldNames) . "')";

            // Add the FetchRelated trait to the controller
            $stub = str_replace(
                "use App\Controllers\\traits\ModelSpecific;",
                "use App\Controllers\\traits\ModelSpecific;\nuse App\Controllers\\traits\FetchRelated;",
                $stub
            );
            $stub = str_replace(
                "use ModelSpecific",
                "use ModelSpecific, FetchRelated",
                $stub
            );

            // Add the fetchAllAsOptions method to the controller
            foreach ($relations as $fieldName => $relatedClassName) {
                $fetchRelatedOptions[] = "\$data['{$fieldName}Options'] = \$this->fetchAllAsOptions('{$relatedClassName}Model');";
            }
        }

        // Replace placeholders
        $stub = str_replace([
            '{{className}}',
            '{{controllerName}}',
            '{{humanEntityName}}',
            '{{baseRoute}}',
            '{{withEagerFetchOnShow}}',
            '{{fetchRelatedOptions}}'
        ], [
            $entityNames['class'],
            $controllerName,
            $entityNames['human'],
            $entityNames['slug'],
            $withEagerFetchOnShow,
            implode("\n        ", $fetchRelatedOptions)
        ], $stub);

        $this->writeFile($path, $stub, 'Controller');
    }


    /**
     * Generate the NavigationCell class.
     */
    protected function generateNavigationCell(array $menuEntities)
    {
        $path = APPPATH . "Cells/NavigationCell.php";
        $stub = file_get_contents(__DIR__ . '/Stubs/NavigationCell.stub');

        // Build the menu entities string
        $menuEntitiesString = '';
        foreach ($menuEntities as $menuEntity) {
            $escapedTitle = str_replace(["\\", "'", '"'], ["\\\\", "\\'", '\\"'], $menuEntity['title']);
            $iconElement = $menuEntity['iconElement'] ?? '';
            $menuEntitiesString .= <<<PHP
                    [
                        'title' => '{$escapedTitle}',
                        'url'   => '{$menuEntity['url']}',
                        'iconElement' => '{$iconElement}',
                    ],

PHP;
        }

        // Replace placeholders
        $stub = str_replace([
            '{{menuEntities}}',
        ], [
            $menuEntitiesString,
        ], $stub);

        $this->writeFile($path, $stub, 'Cell');
    }

    /**
     * Generate the Migration.
     */
    protected function generateMigration(array $entityNames, bool $useDates, array $fields)
    {
        // e.g. "2025-01-31-123456_CreateUsersTable.php"
        $timestamp     = date('Y-m-d-His');
        $migrationName = "Create" . $entityNames['class'] . "Table";
        $path          = APPPATH . "Database/Migrations/{$timestamp}_{$migrationName}.php";

        $stub = file_get_contents(__DIR__ . '/Stubs/Migration.stub');

        // Build the field array portion
        // Example: 
        //   "'title' => [ 'type' => 'VARCHAR', 'constraint' => '255', 'null' => false],"
        $migrationFields = $this->prepareMigrationFields($fields, $useDates);

        list($primaryKeyType, $primaryKeyField) = $this->getPrimaryKeyField($fields);
        $defaultPrimaryKeyDefinition = '';
        if ($primaryKeyType == 'int' && $primaryKeyField == 'id') {
            $defaultPrimaryKeyDefinition = <<<PHP
            // Default primary key
            'id' => [
                'type'           => 'INT',
                'constraint'     => 9,
                'unsigned'       => true,
                'auto_increment' => true
            ],

PHP;
        }

        // Replace placeholders
        $stub = str_replace([
            '{{migrationName}}',
            '{{tablePlural}}',
            '{{migrationFields}}',
            '{{primaryKeyField}}',
            '{{defaultPrimaryKeyDefinition}}'
        ], [
            $migrationName,
            $entityNames['table'],
            $migrationFields,
            $primaryKeyField,
            $defaultPrimaryKeyDefinition
        ], $stub);

        $this->writeFile($path, $stub, 'Migration');
    }

    /**
     * Generate the Seeder.
     */
    protected function generateSeeder(array $entityNames, array $relations, array $seeds = [])
    {
        $seederName = "{$entityNames['class']}Seeder";
        $path       = APPPATH . "Database/Seeds/{$seederName}.php";

        $stub = file_get_contents(__DIR__ . '/Stubs/Seeder.stub');

        // Build the JSON seeds portion
        $jsonSeeds = json_encode($seeds, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        // Trim off the outer brackets
        $jsonSeeds = substr($jsonSeeds, 1, -1);

        // If there are relations, add them to the seeder
        $foreignKeyTransforms = '';
        foreach ($relations as $fieldName => $relatedClassName) {
            $relatedModel = $relatedClassName . 'Model';
            $foreignKeyTransforms .= <<<PHP
        // If needed, transform the $relatedClassName display names to primary keys
        \$temp{$relatedModel} = model('{$relatedModel}');
        if (\$temp{$relatedModel}->useAutoIncrement) {
            \$seedData = populateForeignKeys(\$temp{$relatedModel}, \$seedData, '$fieldName');
        }

PHP;
        }

        // Replace
        $stub = str_replace([
            '{{seederName}}',
            '{{className}}',
            '{{jsonSeeds}}',
            '{{foreignKeyTransforms}}'
        ], [
            $seederName,
            $entityNames['class'],
            $jsonSeeds,
            $foreignKeyTransforms
        ], $stub);

        $this->writeFile($path, $stub, 'Seeder');
    }

    /**
     * Generate an Entity class.
     */
    protected function generateEntity(array $entityNames, bool $useDates, array $fields)
    {
        $entityName = "{$entityNames['class']}Entity";
        $path       = APPPATH . "Entities/{$entityName}.php";

        $stub = file_get_contents(__DIR__ . '/Stubs/Entity.stub');

        // If we do not want dates, remove them from the $dates array
        if (! $useDates) {
            // You could blank it out or remove the property entirely:
            $stub = str_replace(
                "protected \$dates = ['created_at', 'updated_at', 'deleted_at'];",
                "protected \$dates = [];",
                $stub
            );
        }

        // Build the property casting portion
        $casts = $this->preparePropertyCastingFields($fields);

        // Build the secret property getters
        $secretPropertyGetters = $this->prepareSecretPropertyGetters($fields);
        if ($secretPropertyGetters) {
            $secretPropertyGetters = "\n\n    // Secret properties are masked from normal access\n" . $secretPropertyGetters;
            $stub = str_replace(
                "use App\Entities\\traits\DisplayNameAccess;",
                "use App\Entities\\traits\DisplayNameAccess;\nuse App\Entities\\traits\MaskingEntity;",
                $stub
            );
            $stub = str_replace(
                "use PrimaryKeyAccess, DisplayNameAccess",
                "use PrimaryKeyAccess, DisplayNameAccess, MaskingEntity",
                $stub
            );
        }

        list($primaryKeyType, $primaryKeyField) = $this->getPrimaryKeyField($fields);
        $displayNameField = $this->getPrimaryDisplayName($fields);

        $foreignRelationGetters = $this->prepareForeignRelationGetters($fields);

        // Replace
        $stub = str_replace([
            '{{entityName}}',
            '{{casts}}',
            '{{primaryKeyField}}',
            '{{displayNameField}}',
            '{{secretPropertyGetters}}',
            '{{foreignRelationGetters}}'
        ], [
            $entityName,
            $casts,
            $primaryKeyField,
            $displayNameField,
            $secretPropertyGetters,
            $foreignRelationGetters
        ], $stub);

        $this->writeFile($path, $stub, 'Entity');
    }


    /**
     * Generate the Views.
     */
    protected function generateViews(array $entityNames, array $fields)
    {
        $viewsPath = APPPATH . "Views/{$entityNames['class']}";

        // 1) index view
        $indexStub = file_get_contents(__DIR__ . '/Stubs/Views/index.stub');
        // Build table headers & row fields
        $tableHeaders  = $this->generateTableHeaders($fields, 'compact');
        $tableRowCells = $this->generateTableRowCells($fields, $entityNames['slug'], 'compact');

        $indexStub = str_replace([
            '{{humanEntityNamePlural}}',
            '{{baseRoute}}',
            '{{tableHeaders}}',
            '{{tableRowCells}}',
        ], [
            $entityNames['humanPlural'],
            $entityNames['slug'],
            $tableHeaders,
            $tableRowCells,
        ], $indexStub);

        $this->writeFile("{$viewsPath}/index.php", $indexStub, 'View (index)');

        // 2) show view
        $showStub   = file_get_contents(__DIR__ . '/Stubs/Views/show.stub');
        $showFields = $this->generateShowFields($fields);

        $showStub = str_replace([
            '{{humanEntityNamePlural}}',
            '{{baseRoute}}',
            '{{showFields}}',
        ], [
            $entityNames['humanPlural'],
            $entityNames['slug'],
            $showFields,
        ], $showStub);

        $this->writeFile("{$viewsPath}/show.php", $showStub, 'View (show)');

        // 3) new view
        $newStub    = file_get_contents(__DIR__ . '/Stubs/Views/new.stub');
        $formFields = $this->generateFormFields($fields, 'new');
        $newStub    = str_replace([
            '{{humanEntityName}}',
            '{{baseRoute}}',
            '{{formFields}}',
        ], [
            $entityNames['human'],
            $entityNames['slug'],
            $formFields,
        ], $newStub);

        $this->writeFile("{$viewsPath}/new.php", $newStub, 'View (new)');

        // 4) edit view
        $editStub   = file_get_contents(__DIR__ . '/Stubs/Views/edit.stub');
        $formFields = $this->generateFormFields($fields, 'edit');
        $editStub   = str_replace([
            '{{humanEntityName}}',
            '{{baseRoute}}',
            '{{formFields}}',
        ], [
            $entityNames['human'],
            $entityNames['slug'],
            $formFields,
        ], $editStub);

        $this->writeFile("{$viewsPath}/edit.php", $editStub, 'View (edit)');
    }

    /**
     * Generate the <th> columns for the index table.
     */
    protected function generateTableHeaders(array $fields, ?string $projection = null): string
    {
        if (empty($fields)) {
            return "<th>ExampleField</th>";
        }

        $headers = '';
        foreach ($fields as $fieldName => $expectoEntityField) {
            // Filter by projection for the list view
            if ($projection && ! in_array($projection, $expectoEntityField->inProjections)) {
                continue;
            }
            $align = $expectoEntityField->toAlignment(true);
            if (! empty($align)) {
                $align = " style=\"$align\"";
            }

            $label = humanize(decamelize($fieldName));

            $headers .= "<th{$align}>$label</th>";
        }
        return $headers;
    }

    /**
     * Generate the <td> cells for each row in the index.
     */
    protected function generateTableRowCells(array $fields, string $baseRoute, ?string $projection = null): string
    {
        if (empty($fields)) {
            // Example usage
            return "<td><?= esc(\$item['exampleField'] ?? '') ?></td>";
        }

        $rowCells = [];
        foreach ($fields as $fieldName => $expectoEntityField) {
            // Filter by compact only for the list view
            if ($projection && ! in_array($projection, $expectoEntityField->inProjections)) {
                continue;
            }
            $align = $expectoEntityField->toAlignment(true);
            if (! empty($align)) {
                $align = " style=\"$align\"";
            }

            if ($expectoEntityField->primaryDisplayName) {
                $rowCells[] = "<td{$align}><a href=\"<?= site_url('$baseRoute/show/' . rawurlencode(\$item->primaryKey())) ?>\"><?= {$expectoEntityField->escPhp()} ?></a></td>";
            } else {
                $rowCells[] = "<td{$align}><?= {$expectoEntityField->escPhp()} ?></td>";
            }
        }
        return implode("\n            ", $rowCells);
    }

    /**
     * Generate label/value pairs for the show view.
     */
    protected function generateShowFields(array $fields): string
    {
        if (empty($fields)) {
            return "<p>No data found</p>";
        }

        $showFields = [];
        foreach ($fields as $fieldName => $expectoEntityField) {
            if ($expectoEntityField->primaryKey) {
                continue;
            }
            $label = humanize(decamelize($fieldName));
            $showFields[] = "<p><strong>{$label}:</strong> <?= {$expectoEntityField->escPhp()} ?></p>";
        }
        return implode("\n    ", $showFields);
    }

    /**
     * Helper function: Generate form input field HTML
     * 
     * @param string $fieldName The name of the field
     * @param string $label The label for the field
     * @param string $valuePHP The PHP code to output the value
     * @param string $type The input type (default: 'text')
     * @param array $extra Any extra attributes to add to the input, e.g. ['required' => 'required']
     */
    protected function generateFormInputField(string $fieldName, string $label, string $valuePHP, string $type = 'text', array $extra = []): string
    {
        // Convert to PHP code without brackets, e.g. `, 'required' => 'required'`
        $extraStr = $extra ? ', ' . implode(", ", array_map(fn($k, $v) => "'$k' => " . json_encode($v), array_keys($extra), $extra)) : '';
        return <<<HTML
    <p>
        <?= form_label('{$label}', '{$fieldName}') ?> 
        <?= form_input('{$fieldName}', {$valuePHP}, ['id' => '{$fieldName}', 'type' => '{$type}'{$extraStr}]) ?>
    </p>

HTML;
    }

    /**
     * Generate form fields for new/edit views using form helper.
     *
     * For "edit," we typically show the existing value, e.g. `$item->field`.
     */
    protected function generateFormFields(array $fields, string $mode): string
    {
        // TODO: encapsulate the form field generation logic in a helper
        // If no fields are specified, return a single example input
        if (empty($fields)) {
            return $this->generateFormInputField('exampleField', 'Example Field', "set_value('exampleField', 'Example value')");
        }

        $str = '';
        foreach ($fields as $fieldName => $expectoEntityField) {
            // In edit mode, we skip fields which are marked as secret
            if (($mode === 'edit') && ($expectoEntityField->secret ?? false)) {
                continue;
            }

            $label    = humanize(decamelize($fieldName));

            // Populating the form field with the existing value (for 'edit' forms)
            // For new: set_value('fieldName')
            // For edit: set_value('fieldName', $item->fieldName ?? '', false)
            $valuePHP = ($mode === 'edit')
                ? "set_value('{$fieldName}', \$item->{$fieldName} ?? '', false)"
                : "set_value('{$fieldName}', '', false)";

            // Many-to-One relations are displayed as dropdowns
            if ($expectoEntityField->relatedClassName) {
                $relatedClassName = $expectoEntityField->relatedClassName;

                $str .= <<<HTML
    <p>
        <?= form_label('{$label}', '{$fieldName}') ?>
        <?= form_dropdown('{$fieldName}', \${$fieldName}Options, $valuePHP, ['id' => '{$fieldName}']) ?>
    </p>

HTML;
                continue;
            }

            // For boolean fields, we use `form_radio` instead of checkboxes
            // This is because checkboxes are not sent if they are not checked
            // and they semantically represent sets of options, not binary choices
            if ($expectoEntityField->type === ExpectoFieldType::BOOLEAN) {
                $str .= <<<HTML
    <p>
        <?= form_label('{$label}', '{$fieldName}') ?> 
        <?= form_radio('{$fieldName}', '1', set_value('{$fieldName}', \$item->{$fieldName} ?? '') == '1', ['id' => '{$fieldName}']) ?>Yes
        <?= form_radio('{$fieldName}', '0', set_value('{$fieldName}', \$item->{$fieldName} ?? '0') == '0', ['id' => '{$fieldName}']) ?>No
    </p>

HTML;
                continue;
            }

            // For text fields, we use `form_textarea`
            if ($expectoEntityField->type === ExpectoFieldType::TEXT) {
                $str .= <<<HTML
    <p>
        <?= form_label('{$label}', '{$fieldName}') ?> 
        <?= form_textarea('{$fieldName}', {$valuePHP}, ['id' => '{$fieldName}']) ?>
    </p>

HTML;
                continue;
            }

            // The rest of the fields only differ in the input type
            $inputType = 'text';
            $extra = [];

            if ($expectoEntityField->type === ExpectoFieldType::STRING && $expectoEntityField->format == ExpectoFieldFormat::DATETIME)
            {
                // For string fields with datetime format, we use `form_input` with type="datetime-local"
                $inputType = 'datetime-local';
            }
            elseif ($expectoEntityField->type === ExpectoFieldType::STRING && in_array($expectoEntityField->format, [ExpectoFieldFormat::EMAIL, ExpectoFieldFormat::URL]))
            {
                // Formats 'email' and 'url' have special input types
                $inputType = $expectoEntityField->format->value;
            }
            else
            {
                $inputType = match($expectoEntityField->type)
                {
                    ExpectoFieldType::DATETIME => 'datetime-local',
                    ExpectoFieldType::TIMESTAMP => 'number',
                    ExpectoFieldType::DATE => 'date',
                    ExpectoFieldType::STRING, ExpectoFieldType::FLOAT => 'text',
                    ExpectoFieldType::INTEGER => 'number',
                    default => 'text',
                };

                // For string and float fields, we set a max length
                if (in_array($expectoEntityField->type, [ExpectoFieldType::STRING, ExpectoFieldType::FLOAT])) {
                    if ($expectoEntityField->getMaxLength()) {
                        $extra['maxlength'] = $expectoEntityField->getMaxLength();
                    }
                }
            }

            $str .= $this->generateFormInputField($fieldName, $label, $valuePHP, $inputType, $extra);
        }

        return $str;
    }

    /**
     * Prepare Model's allowed fields from JSON-specified $fields.
     */
    protected function prepareAllowedFields(array $fields): string
    {
        // If no fields provided, leave it empty with a comment
        if (empty($fields)) {
            return "        // 'title', 'slug', ...";
        }

        // E.g. ['title' => ['type'=>'String'], 'slug' => ['type'=>'String']]
        // We only need the keys for allowedFields:
        $keys   = array_keys($fields);
        $quoted = array_map(fn($f) => "'{$f}'", $keys);

        return implode(",\n        ", $quoted);
    }

    /**
     * Prepare Model's omittable fields from JSON-specified $fields.
     */
    protected function prepareOmittableFields(array $fields): string
    {
        // If no fields provided, leave it empty
        if (empty($fields)) {
            return "";
        }

        // E.g. `'title', 'content'`
        $omittableFields = array_filter($fields, fn($expectoEntityField) => $expectoEntityField->isOmittable());
        $omittableFieldNames = array_keys($omittableFields);
        $quoted = array_map(fn($f) => "'{$f}'", $omittableFieldNames);

        return implode(", ", $quoted);
    }

    /**
     * Prepare the Validation rules from the JSON $fields.
     */
    protected function prepareValidationRules(array $fields): string
    {
        if (empty($fields)) {
            return "";
        }

        $result = [];
        foreach ($fields as $fieldName => $expectoEntityField) {
            $fieldDef = $expectoEntityField->toValidationRuleString($fieldName);
            if (! $fieldDef) {
                continue;
            }
            $result[] = "'{$fieldName}' => '{$fieldDef}',";
        }

        return implode("\n        ", $result);
    }


    /**
     * Prepare the Migration fields from the JSON $fields.
     */
    protected function prepareMigrationFields(array $fields, bool $useDates): string
    {
        if (empty($fields)) {
            // Just placeholders
            return "            // 'title' => [ 'type' => 'VARCHAR', 'constraint' => 255 ],\n";
        }

        $result = [];
        foreach ($fields as $fieldName => $expectoEntityField) {
            $fieldDef = $expectoEntityField->toForgeFieldPropertyString();

            // Build the line
            $result[] = "'{$fieldName}' => {$fieldDef},";
        }

        // Add timestamps and soft-deletes if requested
        if ($useDates) {
            $result[] = "'created_at' => ['type' => 'DATETIME'],";
            $result[] = "'updated_at' => ['type' => 'DATETIME'],";
            $result[] = "'deleted_at' => ['type' => 'DATETIME', 'null' => true],";
        }

        // Join them with newlines
        return implode("\n            ", $result);
    }


    /**
     * Prepare the Entity Property casting fields from the JSON $fields.
     */
    protected function preparePropertyCastingFields(array $fields): string
    {
        if (empty($fields)) {
            // Just placeholders
            return "        // 'birth_year' => 'integer',\n";
        }

        // Example: 
        //         'full_name'      => 'string',
        //         'birth_year'     => 'integer',
        //         'is_pope'        => 'boolean',
        //         'options'        => 'array',
        //         'options_object' => 'json',
        //         'options_array'  => 'json-array',
        // Built-in types: integer, float, double, string, boolean, object, array, datetime, timestamp, uri and int-bool
        $result = [];
        foreach ($fields as $fieldName => $expectoEntityField) {
            $fieldCastType = $expectoEntityField->toPropertyCast();

            // Build the line if a cast is needed
            if ($fieldCastType) {
                $result[] = "'{$fieldName}' => '{$fieldCastType}',";
            }
        }

        // Join them with newlines
        return implode("\n        ", $result);
    }

    /**
     *  Prepare masking getters for secret properties from the JSON $fields.
     */
    protected function prepareSecretPropertyGetters(array $fields): string
    {
        $secretFields = array_filter($fields, fn($expectoEntityField) => $expectoEntityField->secret ?? false);
        if (empty($secretFields)) {
            return "";
        }

        $secretFieldNames = array_keys($secretFields);
        $quoted = array_map(fn($f) => "'{$f}'", $secretFieldNames);
        $secretFieldsPropertyDefinition = 'public $secretFields = [' . implode(', ', $quoted) . '];';

        $result = [ $secretFieldsPropertyDefinition ];
        foreach ($secretFieldNames as $fieldName) {
            $getter = 'get' . ucfirst($fieldName);
            $result[] = "public function {$getter}(): string { return '***'; }";
        }

        return implode("\n    ", $result);
    }

    /**
     * Prepare foreign relation getters for the Entity from the JSON $fields.
     *     protected function getSeries()
     *     {
     *         return $this->fetchForeign('series', 'LegoSeriesModel');
     *     }
     */
    protected function prepareForeignRelationGetters(array $fields): string
    {
        $foreignFields = array_filter($fields, fn($expectoEntityField) => $expectoEntityField->relatedClassName ?? false);
        if (empty($foreignFields)) {
            return "";
        }

        $result = [];
        foreach ($foreignFields as $fieldName => $expectoEntityField) {
            $relatedModelName = $expectoEntityField->relatedClassName . 'Model';
            $getter = 'get' . ucfirst($fieldName);
            $result[] = "protected function {$getter}() { return \$this->fetchForeign('{$fieldName}', '$relatedModelName'); }";
        }

        return implode("\n    ", $result);
    }


    /**
     * Helper to write file to the filesystem and show console output.
     */
    protected function writeFile(string $path, string $contents, string $type)
    {
        $force = CLI::getOption('force');

        if (! $force && file_exists($path)) {
            CLI::error("Skipping {$type} generation. File already exists: " . CLI::color($path, 'red'));
            CLI::newLine();
            return;
        }

        // Ensure directory exists
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $contents);

        CLI::write("Created {$type}: " . CLI::color($path, 'green'));
    }

    /**
     * Helper to get the primary key field from a JSON spec.
     * 
     * If one of the fields has the 'primaryKey' property set to true, return its type and name.
     * 
     * If there is no primary key field, return 'int' and 'id' as the default.
     */
    protected function getPrimaryKeyField(array $fields): array
    {
        foreach ($fields as $fieldName => $props) {
            if (is_array($props) && array_key_exists('primaryKey', $props) && $props['primaryKey']) {
                return [ $props['type'], $fieldName ];
            }
            elseif ($props instanceof ExpectoEntityField && $props->primaryKey) {
                return [ $props->type, $fieldName ];
            }
        }

        return [ 'int', 'id' ];
    }

    /**
     * Helper to get the field name of the primary display name from a JSON spec.
     */
    protected function getPrimaryDisplayName(array $fields, bool $fallbackToPrimaryKey = true): ?string
    {
        foreach ($fields as $fieldName => $expectoEntityField) {
            if ($expectoEntityField->primaryDisplayName) {
                return $fieldName;
            }
        }

        if ($fallbackToPrimaryKey) {
            list($foreignKeyType, $foreignFieldName) = $this->getPrimaryKeyField($fields);
            return $foreignFieldName;
        }
        return null;
    }

    /**
     * Helper to generate derived names for an entity.
     * 
     * @param string $entityName The name of the entity in camelCase or PascalCase
     * 
     * @return array An array containing the following keys:
     *   - class: The entity name in PascalCase
     *   - human: The entity name in humanized form
     *   - humanPlural: The pluralized humanized entity name
     *   - slug: The entity name in dash-case
     *   - table: The entity name in snake_case
     */
    protected function parseEntityName(string $entityName): array
    {
        return [
            'class'       => ucfirst($entityName),
            'human'       => humanize(decamelize($entityName)),
            'humanPlural' => plural(humanize(decamelize($entityName))),
            'slug'        => dasherize(decamelize($entityName)),
            'table'       => plural(strtolower($entityName)),
        ];
    }

}
