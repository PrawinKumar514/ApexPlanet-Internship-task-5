<?php
class Validator {
    private $errors = [];

    public function required($field, $value) {
        if (empty($value) && $value !== '0') {
            $this->errors[] = "The $field field is required.";
        }
        return $this;
    }

    public function email($field, $value) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "The $field must be a valid email address.";
        }
        return $this;
    }

    public function minLength($field, $value, $min) {
        if (strlen($value) < $min) {
            $this->errors[] = "The $field must be at least $min characters.";
        }
        return $this;
    }

    public function maxLength($field, $value, $max) {
        if (strlen($value) > $max) {
            $this->errors[] = "The $field may not be greater than $max characters.";
        }
        return $this;
    }

    public function match($field, $value, $other) {
        if ($value !== $other) {
            $this->errors[] = "The $field does not match.";
        }
        return $this;
    }

    public function numeric($field, $value) {
        if (!is_numeric($value)) {
            $this->errors[] = "The $field must be a number.";
        }
        return $this;
    }

    public function hasErrors() {
        return !empty($this->errors);
    }

    public function getErrors() {
        return $this->errors;
    }
}
?>