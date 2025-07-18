<?php
namespace App;

class Validator
{
    private $errors = [];

    public function validate($data, $rules)
    {
        $this->errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }

    private function applyRule($field, $value, $rule)
    {
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $ruleValue = $ruleParts[1] ?? null;

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->errors[$field][] = "Le champ {$field} est requis.";
                }
                break;
            
            case 'string':
                if ($value !== null && !is_string($value)) {
                    $this->errors[$field][] = "Le champ {$field} doit être une chaîne de caractères.";
                }
                break;
            
            case 'max':
                if ($value !== null && strlen($value) > (int)$ruleValue) {
                    $this->errors[$field][] = "Le champ {$field} ne doit pas dépasser {$ruleValue} caractères.";
                }
                break;
            
            case 'integer':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->errors[$field][] = "Le champ {$field} doit être un nombre entier.";
                }
                break;
            
            case 'min':
                if ($value !== null && (int)$value < (int)$ruleValue) {
                    $this->errors[$field][] = "Le champ {$field} doit être au moins {$ruleValue}.";
                }
                break;
            
            case 'date':
                if ($value !== null && !$this->isValidDate($value)) {
                    $this->errors[$field][] = "Le champ {$field} doit être une date valide.";
                }
                break;
            
            case 'in':
                $allowedValues = explode(',', $ruleValue);
                if ($value !== null && !in_array($value, $allowedValues)) {
                    $this->errors[$field][] = "Le champ {$field} doit être une des valeurs suivantes: " . implode(', ', $allowedValues);
                }
                break;
            
            case 'regex':
                if ($value !== null && !preg_match($ruleValue, $value)) {
                    $this->errors[$field][] = "Le champ {$field} n'est pas dans le format attendu.";
                }
                break;
            
            case 'array':
                if ($value !== null && !is_array($value)) {
                    $this->errors[$field][] = "Le champ {$field} doit être un tableau.";
                }
                break;
        }
    }

    private function isValidDate($date)
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getErrorMessages()
    {
        $messages = [];
        foreach ($this->errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = $error;
            }
        }
        return $messages;
    }
}