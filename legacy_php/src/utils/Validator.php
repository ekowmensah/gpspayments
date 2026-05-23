<?php
declare(strict_types=1);

namespace App\Utils;

/**
 * Input Validator
 * Validates form inputs and data
 */
class Validator {
    private array $errors = [];
    private array $data = [];
    
    /**
     * Validate data against rules
     * Returns Validator instance for chaining
     */
    public function validate(array $data, array $rules): self {
        $this->data = $data;
        $this->errors = [];
        
        foreach ($rules as $field => $rule) {
            $this->validateField($field, $rule);
        }
        
        return $this;
    }
    
    /**
     * Check if validation passed
     */
    public function passes(): bool {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     */
    public function fails(): bool {
        return !$this->passes();
    }
    
    /**
     * Get all errors
     */
    public function errors(): array {
        return $this->errors;
    }
    
    /**
     * Get errors for specific field
     */
    public function getErrors(string $field): array {
        return $this->errors[$field] ?? [];
    }
    
    /**
     * Validate individual field against rules
     */
    private function validateField(string $field, string $rules): void {
        $rules = explode('|', $rules);
        $value = $this->data[$field] ?? null;
        
        foreach ($rules as $rule) {
            $this->applyRule($field, $rule, $value);
        }
    }
    
    /**
     * Apply a specific validation rule
     */
    private function applyRule(string $field, string $rule, $value): void {
        if (strpos($rule, ':') !== false) {
            [$rule, $param] = explode(':', $rule, 2);
        } else {
            $param = null;
        }
        
        $rule = trim($rule);
        
        match($rule) {
            'required' => $this->validateRequired($field, $value),
            'email' => $this->validateEmail($field, $value),
            'numeric' => $this->validateNumeric($field, $value),
            'integer' => $this->validateInteger($field, $value),
            'min' => $this->validateMin($field, $value, (int)$param),
            'max' => $this->validateMax($field, $value, (int)$param),
            'between' => $this->validateBetween($field, $value, $param),
            'length' => $this->validateLength($field, $value, (int)$param),
            'phone' => $this->validatePhone($field, $value),
            'unique' => $this->validateUnique($field, $value, $param),
            'in' => $this->validateIn($field, $value, $param),
            'regex' => $this->validateRegex($field, $value, $param),
            'date' => $this->validateDate($field, $value),
            'amount' => $this->validateAmount($field, $value),
            default => null
        };
    }
    
    private function validateRequired(string $field, $value): void {
        if (empty($value) && $value !== '0') {
            $this->addError($field, ucfirst($field) . ' is required');
        }
    }
    
    private function validateEmail(string $field, $value): void {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'Invalid email format');
        }
    }
    
    private function validateNumeric(string $field, $value): void {
        if (!empty($value) && !is_numeric($value)) {
            $this->addError($field, ucfirst($field) . ' must be numeric');
        }
    }
    
    private function validateInteger(string $field, $value): void {
        if (!empty($value) && !is_int($value) && !ctype_digit((string)$value)) {
            $this->addError($field, ucfirst($field) . ' must be an integer');
        }
    }
    
    private function validateMin(string $field, $value, int $min): void {
        if (!empty($value) && is_numeric($value) && $value < $min) {
            $this->addError($field, ucfirst($field) . " must be at least $min");
        }
    }
    
    private function validateMax(string $field, $value, int $max): void {
        if (!empty($value) && is_numeric($value) && $value > $max) {
            $this->addError($field, ucfirst($field) . " cannot exceed $max");
        }
    }
    
    private function validateBetween(string $field, $value, string $param): void {
        [$min, $max] = explode(',', $param);
        if (!empty($value) && is_numeric($value)) {
            if ($value < (int)$min || $value > (int)$max) {
                $this->addError($field, ucfirst($field) . " must be between $min and $max");
            }
        }
    }
    
    private function validateLength(string $field, $value, int $length): void {
        if (!empty($value) && strlen((string)$value) !== $length) {
            $this->addError($field, ucfirst($field) . " must be exactly $length characters");
        }
    }
    
    private function validatePhone(string $field, $value): void {
        if (!empty($value)) {
            $phone = preg_replace('/\D/', '', (string)$value);
            if (strlen($phone) < 10 || strlen($phone) > 13) {
                $this->addError($field, 'Invalid phone number format');
            }
        }
    }
    
    private function validateUnique(string $field, $value, string $param): void {
        if (empty($value)) return;
        
        [$table, $column] = explode(',', $param);
        $db = db();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table WHERE $column = ?");
        $stmt->bind_param("s", $value);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $this->addError($field, ucfirst($field) . ' already exists');
        }
    }
    
    private function validateIn(string $field, $value, string $param): void {
        if (empty($value)) return;
        
        $allowed = explode(',', $param);
        $allowed = array_map('trim', $allowed);
        
        if (!in_array((string)$value, $allowed)) {
            $this->addError($field, ucfirst($field) . ' must be one of: ' . $param);
        }
    }
    
    private function validateRegex(string $field, $value, string $pattern): void {
        if (!empty($value) && !preg_match($pattern, (string)$value)) {
            $this->addError($field, ucfirst($field) . ' format is invalid');
        }
    }
    
    private function validateDate(string $field, $value): void {
        if (!empty($value)) {
            $date = \DateTime::createFromFormat('Y-m-d', (string)$value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                $this->addError($field, ucfirst($field) . ' must be a valid date (YYYY-MM-DD)');
            }
        }
    }
    
    private function validateAmount(string $field, $value): void {
        if (empty($value)) return;
        
        if (!is_numeric($value)) {
            $this->addError($field, ucfirst($field) . ' must be a valid amount');
            return;
        }
        
        if ($value < 0.01 || $value > 100000) {
            $this->addError($field, 'Amount must be between 0.01 and 100000');
        }
    }
    
    private function addError(string $field, string $message): void {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
}
?>
