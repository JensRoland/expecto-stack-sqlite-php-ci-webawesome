<?php

namespace App\Libraries;

enum ExpectoFieldType: string
{
    case STRING = 'string';
    case TEXT = 'text';
    case INTEGER = 'int';
    case FLOAT = 'float';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case TIMESTAMP = 'timestamp';

    public function toPropertyCast(): string
    {
        return match($this)
        {
            self::STRING, self::TEXT => 'string',
            self::INTEGER => 'integer',
            self::FLOAT => 'float',
            self::BOOLEAN => 'boolean',
            self::DATE => 'string',
            self::DATETIME => 'datetime',
            self::TIMESTAMP => 'integer'
        };
    }

    public function toDatabaseType(): string
    {
        return match($this)
        {
            self::STRING => 'VARCHAR',
            self::TEXT => 'TEXT',
            self::INTEGER => 'INT',
            self::FLOAT => 'FLOAT',
            self::BOOLEAN => 'BOOLEAN',
            self::DATE => 'VARCHAR',
            self::DATETIME => 'DATETIME',
            self::TIMESTAMP => 'TIMESTAMP'
        };
    }
}

enum ExpectoFieldFormat: string
{
    case ALPHA = 'alpha';
    case ALPHANUMERIC = 'alphanumeric';
    case HEXADECIMAL = 'hexadecimal';
    case TIMEZONE = 'timezone';
    case CC_NUMBER = 'cc_number';
    case DATE = 'date';
    case DATETIME = 'datetime';
    case EMAIL = 'email';
    case IP_ADDRESS = 'ip_address';
    case JSON = 'json';
    case URL = 'url';
    case UUID = 'uuid';
    // TODO: add 'color' format
}

class ExpectoEntityField
{
    public string $name;
    public ExpectoFieldType $type;
    public ?ExpectoFieldFormat $format = null;
    public $default = null;
    public ?int $minLength = null;
    public ?int $maxLength = null;
    public ?string $pattern = null;
    public bool $primaryKey = false;
    public bool $primaryDisplayName = false;
    public ?string $relatedClassName = null; // If this field is a foreign key, the related model class name.
    public bool $required = false;
    public bool $secret = false;
    public bool $unique = false;
    // Which projections (e.g. 'compact') this field is included in.
    public array $inProjections = [];

    /**
     * Create a new ExpectoEntityField instance.
     * 
     * Takes a field name and an associative array of properties as inputs.
     * The properties array looks like:
     * 
     * {
     *   "type": "int",
     *   "required": true,
     *   "default": 0
     * }
     */
    public function __construct(string $name, array $properties)
    {
        $this->name = $name;
        foreach ($properties as $key => $value)
        {
            match ($key)
            {
                'type' => $this->type = ExpectoFieldType::from($value),
                'format' => $this->format = ExpectoFieldFormat::tryFrom($value),
                'default', 'minLength', 'maxLength', 'pattern', 'primaryKey', 'primaryDisplayName', 'relatedClassName', 'required', 'secret', 'unique' => $this->$key = $value,
                default => throw new \InvalidArgumentException("Invalid property for ExpectoEntityField: $key")
            };
        }
        // Primary keys are always required.
        if ($this->primaryKey)
        {
            $this->required = true;
        }
    }

    public function isNullable(): bool
    {
        return ! $this->required;
    }

    public function isOmittable(): bool
    {
        return ! $this->required || $this->default !== null;
    }

    public function escPhp(string $variableName = '$item'): string
    {
        // Escaping the output depends on the field type.
        // Strings use the esc() function, but e.g. integers and booleans do not.
        return match ($this->type) {
            ExpectoFieldType::STRING, ExpectoFieldType::TEXT => "esc({$variableName}->{$this->name})",
            ExpectoFieldType::BOOLEAN => "({$variableName}->{$this->name} ? 'Yes' : 'No')",
            default => "{$variableName}->{$this->name}",
        };
    }

    public function toAlignment(bool $ignoreLeftAlign=false, bool $unwrapped=false): string
    {
        $alignment = match ($this->type) {
            ExpectoFieldType::INTEGER, ExpectoFieldType::FLOAT, ExpectoFieldType::TIMESTAMP => 'right',
            default => 'left',
        };
        if ($ignoreLeftAlign && $alignment == 'left') {
            return '';
        }
        return $unwrapped ? $alignment : "text-align: $alignment;";
    }

    public function getMaxLength(): ?int
    {
        if ($this->maxLength !== null)
        {
            return $this->maxLength;
        }
        if ($this->format === ExpectoFieldFormat::UUID)
        {
            return 36;
        }
        if ($this->type === ExpectoFieldType::STRING)
        {
            return 255;
        }
        return null;
    }

    public function toPropertyCast(): ?string
    {
        if ($this->relatedClassName)
        {
            return null;
        }
        return $this->type->toPropertyCast();
    }

    public function toForgeFieldProperties(): array
    {
        $properties = [];
        $properties['type'] = $this->type->toDatabaseType();
        if ($this->getMaxLength() !== null)
        {
            $properties['constraint'] = $this->getMaxLength();
        }
        if ($this->isNullable())
        {
            $properties['null'] = true;
        }
        if ($this->unique)
        {
            $properties['unique'] = true;
        }
        if ($this->default !== null)
        {
            $properties['default'] = $this->default;
        }
        return $properties;
    }

    public function toForgeFieldPropertyString(): string
    {
        $properties = $this->toForgeFieldProperties();
        $propertyStrings = [];
        foreach ($properties as $key => $value)
        {
            $propertyStrings[] = "'$key' => " . json_encode($value);
        }
        return '[' . implode(', ', $propertyStrings) . ']';
    }


    public function toValidationRules(string $tableName): array
    {
        $rules = [];
        if (! $this->isOmittable())
        {
            $rules[] = 'required';
        }
        else 
        {
            $rules[] = 'permit_empty';
        }
        if ($this->minLength !== null)
        {
            $rules[] = "min_length[{$this->minLength}]";
        }
        if ($this->getMaxLength() !== null)
        {
            $rules[] = "max_length[{$this->getMaxLength()}]";
        }
        if ($this->pattern)
        {
            $rules[] = "regex_match[{$this->pattern}]";
        }
        if ($this->unique)
        {
            $rules[] = "is_unique[{$tableName}.{$this->name}]";
        }
        if ($this->type === ExpectoFieldType::STRING && $this->format)
        {
            $rules[] = match($this->format) 
            { 
                ExpectoFieldFormat::ALPHA => 'alpha',
                ExpectoFieldFormat::ALPHANUMERIC => 'alpha_numeric',
                ExpectoFieldFormat::HEXADECIMAL => 'hex',
                ExpectoFieldFormat::TIMEZONE => 'timezone',
                ExpectoFieldFormat::CC_NUMBER => 'valid_cc_number',
                ExpectoFieldFormat::DATE => 'valid_date[Y-m-d]',
                ExpectoFieldFormat::DATETIME => 'valid_date[Y-m-d H:i:s]',
                ExpectoFieldFormat::EMAIL => 'valid_email',
                ExpectoFieldFormat::IP_ADDRESS => 'valid_ip',
                ExpectoFieldFormat::JSON => 'valid_json',
                ExpectoFieldFormat::URL => 'valid_url',
                ExpectoFieldFormat::UUID => 'valid_uuidv7'
            };
        }
        if ($this->type === ExpectoFieldType::INTEGER)
        {
            $rules[] = 'integer';
        }
        if ($this->type === ExpectoFieldType::FLOAT)
        {
            $rules[] = 'decimal';
        }
        if ($this->type === ExpectoFieldType::BOOLEAN)
        {
            $rules[] = 'in_list[1,0,true,false]';
        }
        if ($this->type === ExpectoFieldType::DATE)
        {
            $rules[] = 'valid_date[Y-m-d]';
        }
        if ($this->type === ExpectoFieldType::DATETIME)
        {
            // TODO: Add a `dateFormat` property to the field for more expressive date formats.
            $rules[] = 'valid_date[Y-m-d H:i:s]';
        }
        if ($this->type === ExpectoFieldType::TIMESTAMP)
        {
            $rules[] = 'numeric';
        }
        if ($this->primaryKey && $this->type === ExpectoFieldType::STRING)
        {
            $rules[] = 'regex_match[/\A[' . config('App')->permittedURIChars . ']+\z/iu]';
        }
        return $rules;
    }

    public function toValidationRuleString(string $tableName): string
    {
        return implode('|', $this->toValidationRules($tableName));
    }
}
