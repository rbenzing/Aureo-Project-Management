<?php

// file: Utils/Validator.php
declare(strict_types=1);

namespace App\Utils;

use App\Core\Database;
use InvalidArgumentException;
use PDOException;

class Validator
{
    private Database $db;
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $sanitizedData = [];

    /**
     * Available validation rules
     */
    private const AVAILABLE_RULES = [
        'required',
        'string',
        'email',
        'unique',
        'max',
        'min',
        'integer',
        'boolean',
        'in',
        'date',
        'nullable',
        'same',
        'alpha',
        'alphanumeric',
        'url',
        'phone',
        'json',
        'array',
        'exists',
        'after',
        'strong_password',
        'enum',
    ];

    /**
     * Custom error messages
     */
    private array $customMessages = [
        'required' => ':field is required.',
        'string' => ':field must be a string.',
        'email' => ':field must be a valid email address.',
        'unique' => ':field already exists.',
        'max' => ':field must not exceed :param characters.',
        'min' => ':field must be at least :param characters.',
        'integer' => ':field must be an integer.',
        'boolean' => ':field must be a boolean value.',
        'in' => ':field must be one of: :param.',
        'date' => ':field must be a valid date.',
        'same' => ':field must match :param.',
        'alpha' => ':field must contain only letters.',
        'alphanumeric' => ':field must contain only letters and numbers.',
        'url' => ':field must be a valid URL.',
        'phone' => ':field must be a valid phone number.',
        'json' => ':field must be valid JSON.',
        'array' => ':field must be an array.',
        'exists' => ':field does not exist in the database.',
        'after' => ':field must be a date after :param.',
        'strong_password' => ':field must be at least 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char.',
        'enum' => ':field must be a valid value.',
    ];

    public function __construct(array $data, array $rules)
    {
        $this->db = Database::getInstance();
        $this->data = $data;
        $this->rules = $this->parseRules($rules);
        $this->sanitizedData = $data;
    }

    /**
     * Parse validation rules into a structured format
     */
    private function parseRules(array $rules): array
    {
        $parsed = [];
        foreach ($rules as $field => $ruleSet) {
            $fieldRules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $parsed[$field] = array_map(function ($rule) {
                $parts = explode(':', $rule);

                return [
                    'name' => $parts[0],
                    'parameters' => isset($parts[1]) ? explode(',', $parts[1]) : [],
                ];
            }, $fieldRules);
        }

        return $parsed;
    }

    /**
     * Run validation
     */
    public function fails(): bool
    {
        $this->errors = [];
        foreach ($this->rules as $field => $rules) {

            foreach ($rules as $rule) {
                if (!in_array($rule['name'], self::AVAILABLE_RULES)) {

                    throw new InvalidArgumentException("Unknown validation rule: {$rule['name']}");
                }
                $this->validateField($field, $rule);
            }
        }

        return !empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get sanitized data
     */
    public function sanitized(): array
    {
        return $this->sanitizedData;
    }

    /**
     * Validate a single field
     */
    private function validateField(string $field, array $rule): void
    {
        $value = $this->sanitizedData[$field] ?? null;
        $ruleName = $rule['name'];
        $parameters = $rule['parameters'];

        // Skip validation for nullable fields if value is empty
        if ($this->isNullable($field) && $this->isEmpty($value)) {
            return;
        }

        try {
            $methodName = 'validate' . ucfirst($ruleName);
            if (method_exists($this, $methodName)) {
                $this->$methodName($field, $value, $parameters);
            }
        } catch (PDOException $e) {
            $this->addError($field, "Database error occurred while validating $field");
            error_log("Validation database error: " . $e->getMessage());
        }
    }

    /**
     * Check if a field is nullable
     */
    private function isNullable(string $field): bool
    {
        foreach ($this->rules[$field] as $rule) {
            if ($rule['name'] === 'nullable') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a value is empty
     */
    private function isEmpty($value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * Add an error message
     */
    private function addError(string $field, string $rule, array $parameters = []): void
    {
        $message = $this->customMessages[$rule] ?? ":field failed $rule validation.";
        $message = str_replace(':field', ucfirst($field), $message);
        if (!empty($parameters)) {
            $message = str_replace(':param', implode(', ', $parameters), $message);
        }
        $this->errors[$field] = $message;
    }

    /**
     * Validation methods
     */
    private function validateRequired(string $field, $value): void
    {
        if ($this->isEmpty($value)) {
            $this->addError($field, 'required');
        }
    }

    private function validateString(string $field, $value): void
    {
        if (!is_null($value) && !is_string($value)) {
            $this->addError($field, 'string');
        }
        $this->sanitizedData[$field] = is_string($value) ?
            htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8') : $value;
    }

    private function validateEmail(string $field, $value): void
    {
        if (!is_null($value)) {
            $sanitized = filter_var($value, FILTER_SANITIZE_EMAIL);
            if (!filter_var($sanitized, FILTER_VALIDATE_EMAIL)) {
                $this->addError($field, 'email');
            }
            $this->sanitizedData[$field] = $sanitized;
        }
    }

    private function validateUnique(string $field, $value, array $parameters): void
    {
        if (empty($value) || empty($parameters)) {
            return;
        }

        [$table, $column] = $parameters;

        // Validate table and column names
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new InvalidArgumentException("Invalid table or column name in unique validation");
        }

        $excludeId = $this->data['id'] ?? null;

        try {
            $query = "SELECT COUNT(*) FROM `$table` WHERE `$column` = :value";
            $params = [':value' => $value];

            if ($excludeId) {
                $query .= " AND id != :id";
                $params[':id'] = $excludeId;
            }

            $stmt = $this->db->executeQuery($query, $params);
            if ($stmt->fetchColumn() > 0) {
                $this->addError($field, 'unique');
            }
        } catch (PDOException $e) {
            throw new PDOException("Database error in unique validation: " . $e->getMessage());
        }
    }

    private function validateMax(string $field, $value, array $parameters): void
    {
        if (!is_null($value) && isset($parameters[0]) && strlen($value) > (int)$parameters[0]) {
            $this->addError($field, 'max', $parameters);
        }
    }

    private function validateMin(string $field, $value, array $parameters): void
    {
        if (!is_null($value) && isset($parameters[0]) && strlen($value) < (int)$parameters[0]) {
            $this->addError($field, 'min', $parameters);
        }
    }

    private function validateInteger(string $field, $value): void
    {
        if (!is_null($value)) {
            $sanitized = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            if (!filter_var($sanitized, FILTER_VALIDATE_INT)) {
                $this->addError($field, 'integer');
            }
            $this->sanitizedData[$field] = $sanitized;
        }
    }

    private function validateBoolean(string $field, $value): void
    {
        if (!is_null($value) && !in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            $this->addError($field, 'boolean');
        }
    }

    private function validateIn(string $field, $value, array $parameters): void
    {
        if (!is_null($value) && !in_array($value, $parameters)) {
            $this->addError($field, 'in', $parameters);
        }
    }

    private function validateDate(string $field, $value): void
    {
        if (!is_null($value) && !strtotime($value)) {
            $this->addError($field, 'date');
        }
    }

    private function validateSame(string $field, $value, array $parameters): void
    {
        if (isset($parameters[0]) && $value !== ($this->data[$parameters[0]] ?? null)) {
            $this->addError($field, 'same', $parameters);
        }
    }

    private function validateAlpha(string $field, $value): void
    {
        if (!is_null($value) && !ctype_alpha(str_replace(' ', '', $value))) {
            $this->addError($field, 'alpha');
        }
    }

    private function validateAlphanumeric(string $field, $value): void
    {
        if (!is_null($value) && !ctype_alnum(str_replace(' ', '', $value))) {
            $this->addError($field, 'alphanumeric');
        }
    }

    private function validateUrl(string $field, $value): void
    {
        if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url');
        }
    }

    private function validatePhone(string $field, $value): void
    {
        if (!is_null($value)) {
            $sanitized = preg_replace('/[^0-9+()-]/', '', $value);
            if (!preg_match('/^[+]?[\d()-]{10,}$/', $sanitized)) {
                $this->addError($field, 'phone');
            }
            $this->sanitizedData[$field] = $sanitized;
        }
    }

    private function validateJson(string $field, $value): void
    {
        if (!is_null($value)) {
            json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError($field, 'json');
            }
        }
    }

    private function validateArray(string $field, $value): void
    {
        if (!is_null($value) && !is_array($value)) {
            $this->addError($field, 'array');
        }
    }

    private function validateExists(string $field, $value, array $parameters): void
    {
        if (empty($value) || empty($parameters)) {
            return;
        }

        [$table, $column] = $parameters;

        // Validate table and column names
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            throw new InvalidArgumentException("Invalid table or column name in unique validation");
        }

        try {
            $query = "SELECT COUNT(*) as cnt FROM `$table` WHERE `$column` = :value";
            $params = [':value' => $value];
            $stmt = $this->db->executeQuery($query, $params);
            if (!$stmt->fetchColumn()) {
                $this->addError($field, 'exists');
            }
        } catch (PDOException $e) {
            throw new PDOException("Database error in exists validation: " . $e->getMessage());
        }
    }

    private function validateAfter(string $field, $value, array $parameters): void
    {
        if (empty($value) || empty($parameters)) {
            return;
        }

        // If the parameter is a field name, get the date from that field
        $compareValue = $parameters[0];
        if (isset($this->data[$compareValue])) {
            $compareValue = $this->data[$compareValue];
        }

        // Convert both to timestamps for comparison
        $date1 = strtotime($value);
        $date2 = strtotime($compareValue);

        if (!$date1 || !$date2 || $date1 <= $date2) {
            $this->addError($field, 'after', $parameters);
        }
    }

    private function validateStrongPassword(string $field, $value): void
    {
        if (!is_null($value)) {
            // Check for at least 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
            $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
            if (!preg_match($pattern, $value)) {
                $this->addError($field, 'strong_password');
            }
        }
    }

    /**
     * Validate enum value
     *
     * Usage: 'field' => ['required', 'enum:App\Enums\TaskType']
     */
    private function validateEnum(string $field, $value, array $parameters): void
    {
        if (empty($parameters[0])) {
            throw new InvalidArgumentException("Enum validation requires an enum class name");
        }

        $enumClass = $parameters[0];

        // Check if class exists and is an enum
        if (!enum_exists($enumClass)) {
            throw new InvalidArgumentException("Invalid enum class: {$enumClass}");
        }

        // Get all valid enum values
        $validValues = array_column($enumClass::cases(), 'value');

        // Check if the value is valid
        if (!is_null($value) && !in_array($value, $validValues, true)) {
            $this->addError($field, 'enum', $validValues);
        }
    }
}
