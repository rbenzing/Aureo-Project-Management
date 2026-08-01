<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Exceptions\ValidationException;

/**
 * Base Form Request Class
 *
 * Provides validation and authorization for incoming requests
 */
abstract class FormRequest
{
    protected array $data = [];
    protected array $errors = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Get validation rules
     *
     * @return array
     */
    abstract protected function rules(): array;

    /**
     * Custom validation messages (optional)
     *
     * @return array
     */
    protected function messages(): array
    {
        return [];
    }

    /**
     * Authorize the request (optional)
     *
     * @return bool
     */
    protected function authorize(): bool
    {
        return true;
    }

    /**
     * Validate the request
     *
     * @throws ValidationException
     * @return array Validated data
     */
    public function validate(): array
    {
        // Check authorization
        if (!$this->authorize()) {
            throw new \App\Exceptions\AuthorizationException('This action is unauthorized');
        }

        // Perform validation
        $rules = $this->rules();
        $messages = $this->messages();

        foreach ($rules as $field => $fieldRules) {
            $value = $this->data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule, $messages);
            }
        }

        if (!empty($this->errors)) {
            throw ValidationException::withErrors($this->errors);
        }

        // Return only validated fields
        return array_intersect_key($this->data, $rules);
    }

    /**
     * Apply a validation rule
     *
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @param array $messages
     */
    protected function applyRule(string $field, mixed $value, string $rule, array $messages): void
    {
        // Parse rule and parameters
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $ruleParams = isset($ruleParts[1]) ? explode(',', $ruleParts[1]) : [];

        $passed = match ($ruleName) {
            'required' => $this->validateRequired($value),
            'string' => $this->validateString($value),
            'integer' => $this->validateInteger($value),
            'numeric' => $this->validateNumeric($value),
            'email' => $this->validateEmail($value),
            'url' => $this->validateUrl($value),
            'min' => $this->validateMin($value, (int)($ruleParams[0] ?? 0)),
            'max' => $this->validateMax($value, (int)($ruleParams[0] ?? 0)),
            'between' => $this->validateBetween($value, (int)($ruleParams[0] ?? 0), (int)($ruleParams[1] ?? 0)),
            'in' => $this->validateIn($value, $ruleParams),
            'date' => $this->validateDate($value),
            'boolean' => $this->validateBoolean($value),
            'array' => $this->validateArray($value),
            'exists' => true, // Placeholder - implement database check if needed
            'unique' => true, // Placeholder - implement database check if needed
            default => true,
        };

        if (!$passed) {
            $this->addError($field, $this->getErrorMessage($field, $ruleName, $ruleParams, $messages));
        }
    }

    /**
     * Validate required field
     */
    protected function validateRequired(mixed $value): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }

    /**
     * Validate string
     */
    protected function validateString(mixed $value): bool
    {
        return is_null($value) || is_string($value);
    }

    /**
     * Validate integer
     */
    protected function validateInteger(mixed $value): bool
    {
        return is_null($value) || filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate numeric
     */
    protected function validateNumeric(mixed $value): bool
    {
        return is_null($value) || is_numeric($value);
    }

    /**
     * Validate email
     */
    protected function validateEmail(mixed $value): bool
    {
        return is_null($value) || filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate URL
     */
    protected function validateUrl(mixed $value): bool
    {
        return is_null($value) || filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate minimum length/value
     */
    protected function validateMin(mixed $value, int $min): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return strlen($value) >= $min;
        }

        if (is_numeric($value)) {
            return $value >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return false;
    }

    /**
     * Validate maximum length/value
     */
    protected function validateMax(mixed $value, int $max): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value)) {
            return strlen($value) <= $max;
        }

        if (is_numeric($value)) {
            return $value <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return false;
    }

    /**
     * Validate between range
     */
    protected function validateBetween(mixed $value, int $min, int $max): bool
    {
        return $this->validateMin($value, $min) && $this->validateMax($value, $max);
    }

    /**
     * Validate that a value is one of an allowed set.
     *
     * Rule parameters always arrive as strings, because they are produced by
     * splitting "in:1,2,3" on the comma. Comparing them strictly against the raw
     * value therefore rejected integers that ARE in the list — a caller passing
     * ProjectStatus::READY->value (int 1) was told "must be one of 1,2,3", which
     * is self-contradictory and near-impossible to debug. Form posts happened to
     * work only because $_POST values are already strings.
     *
     * Both sides are normalised to strings rather than dropping the strict flag:
     * loose comparison would silently accept its own surprises, and stating the
     * intent is better than relying on juggling rules.
     */
    protected function validateIn(mixed $value, array $allowed): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (!is_scalar($value)) {
            return false;
        }

        $allowedAsStrings = array_map(static fn (mixed $entry): string => (string) $entry, $allowed);

        return in_array((string) $value, $allowedAsStrings, true);
    }

    /**
     * Validate date
     */
    protected function validateDate(mixed $value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return strtotime($value) !== false;
    }

    /**
     * Validate boolean
     */
    protected function validateBoolean(mixed $value): bool
    {
        return is_null($value) || in_array($value, [true, false, 0, 1, '0', '1'], true);
    }

    /**
     * Validate array
     */
    protected function validateArray(mixed $value): bool
    {
        return is_null($value) || is_array($value);
    }

    /**
     * Add validation error
     */
    protected function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Get error message
     */
    protected function getErrorMessage(string $field, string $rule, array $params, array $messages): string
    {
        $key = "{$field}.{$rule}";

        if (isset($messages[$key])) {
            return $messages[$key];
        }

        // Default messages
        return match ($rule) {
            'required' => "The {$field} field is required",
            'string' => "The {$field} must be a string",
            'integer' => "The {$field} must be an integer",
            'numeric' => "The {$field} must be numeric",
            'email' => "The {$field} must be a valid email address",
            'url' => "The {$field} must be a valid URL",
            'min' => "The {$field} must be at least {$params[0]}",
            'max' => "The {$field} must not exceed {$params[0]}",
            'between' => "The {$field} must be between {$params[0]} and {$params[1]}",
            'in' => "The selected {$field} is invalid",
            'date' => "The {$field} must be a valid date",
            'boolean' => "The {$field} must be true or false",
            'array' => "The {$field} must be an array",
            default => "The {$field} is invalid",
        };
    }

    /**
     * Get validated data
     *
     * @return array
     */
    public function validated(): array
    {
        return $this->validate();
    }

    /**
     * Get specific field value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if field exists
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }
}
