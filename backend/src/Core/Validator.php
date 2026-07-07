<?php

namespace App\Core;

/**
 * Small, dependency-free validator.
 * Rules are pipe-separated strings, e.g. "required|string|max:255".
 */
class Validator
{
    private array $errors = [];

    public static function make(array $data, array $rules): self
    {
        $v = new self();
        $v->validate($data, $rules);
        return $v;
    }

    private function validate(array $data, array $rules): void
    {
        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                switch ($rule) {
                    case 'required':
                        if ($value === null || $value === '') {
                            $this->addError($field, "The $field field is required.");
                        }
                        break;
                    case 'string':
                        if ($value !== null && !is_string($value)) {
                            $this->addError($field, "The $field field must be a string.");
                        }
                        break;
                    case 'numeric':
                        if ($value !== null && !is_numeric($value)) {
                            $this->addError($field, "The $field field must be numeric.");
                        }
                        break;
                    case 'integer':
                        if ($value !== null && filter_var($value, FILTER_VALIDATE_INT) === false) {
                            $this->addError($field, "The $field field must be an integer.");
                        }
                        break;
                    case 'email':
                        if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $this->addError($field, "The $field field must be a valid email.");
                        }
                        break;
                    case 'min':
                        if ($value !== null && is_string($value) && strlen($value) < (int)$params[0]) {
                            $this->addError($field, "The $field field must be at least {$params[0]} characters.");
                        }
                        break;
                    case 'max':
                        if ($value !== null && is_string($value) && strlen($value) > (int)$params[0]) {
                            $this->addError($field, "The $field field may not be greater than {$params[0]} characters.");
                        }
                        break;
                    case 'in':
                        if ($value !== null && !in_array($value, $params, true)) {
                            $this->addError($field, "The $field field must be one of: " . implode(', ', $params) . '.');
                        }
                        break;
                    case 'array':
                        if ($value !== null && !is_array($value)) {
                            $this->addError($field, "The $field field must be an array.");
                        }
                        break;
                }
            }
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
