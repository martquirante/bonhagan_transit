<?php
/**
 * BONHAGAN TRANSIT — Data Validation Helper
 */

class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public static function make(array $data): self {
        return new self($data);
    }

    public function required(string $field, ?string $customMessage = null): self {
        if (!isset($this->data[$field]) || trim((string)$this->data[$field]) === '') {
            $this->addError($field, $customMessage ?? "The {$field} field is required.");
        }
        return $this;
    }

    public function email(string $field, ?string $customMessage = null): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                $this->addError($field, $customMessage ?? "The {$field} must be a valid email address.");
            }
        }
        return $this;
    }

    public function minLength(string $field, int $length, ?string $customMessage = null): self {
        if (isset($this->data[$field]) && mb_strlen((string)$this->data[$field]) < $length) {
            $this->addError($field, $customMessage ?? "The {$field} must be at least {$length} characters.");
        }
        return $this;
    }

    public function maxLength(string $field, int $length, ?string $customMessage = null): self {
        if (isset($this->data[$field]) && mb_strlen((string)$this->data[$field]) > $length) {
            $this->addError($field, $customMessage ?? "The {$field} must not exceed {$length} characters.");
        }
        return $this;
    }

    public function in(string $field, array $allowed, ?string $customMessage = null): self {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $allowedList = implode(', ', $allowed);
            $this->addError($field, $customMessage ?? "The {$field} must be one of: {$allowedList}.");
        }
        return $this;
    }

    public function numeric(string $field, ?string $customMessage = null): self {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->addError($field, $customMessage ?? "The {$field} must be a numeric value.");
        }
        return $this;
    }

    public function integer(string $field, ?string $customMessage = null): self {
        if (isset($this->data[$field]) && filter_var($this->data[$field], FILTER_VALIDATE_INT) === false) {
            $this->addError($field, $customMessage ?? "The {$field} must be a valid integer.");
        }
        return $this;
    }

    public function date(string $field, string $format = 'Y-m-d', ?string $customMessage = null): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $d = DateTime::createFromFormat($format, $this->data[$field]);
            if (!($d && $d->format($format) === $this->data[$field])) {
                $this->addError($field, $customMessage ?? "The {$field} must be a valid date in format {$format}.");
            }
        }
        return $this;
    }

    public function addError(string $field, string $message): void {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    public function fails(): bool {
        return !empty($this->errors);
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public static function sanitize(mixed $value): mixed {
        if (is_array($value)) {
            return array_map([self::class, 'sanitize'], $value);
        }
        if (is_string($value)) {
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
        return $value;
    }
}

