<?php

namespace App\Service;

use App\Entity\FormField;

/**
 * Registry service for managing form field types and their configurations
 */
class FormFieldTypeRegistry
{
    private array $fieldTypes = [];
    private array $fieldTypeConfigs = [];

    public function __construct()
    {
        $this->initializeFieldTypes();
    }

    /**
     * Initialize all available field types with their configurations
     */
    private function initializeFieldTypes(): void
    {
        $this->fieldTypes = [
            FormField::TYPE_TEXT => [
                'name' => 'Text Input',
                'description' => 'Single line text input field',
                'icon' => 'text-fields',
                'category' => 'basic',
                'supports_placeholder' => true,
                'supports_default_value' => true,
                'supports_validation' => true,
                'supports_options' => false,
                'html_input_type' => 'text',
            ],
            FormField::TYPE_TEXTAREA => [
                'name' => 'Textarea',
                'description' => 'Multi-line text input field',
                'icon' => 'text-format',
                'category' => 'basic',
                'supports_placeholder' => true,
                'supports_default_value' => true,
                'supports_validation' => true,
                'supports_options' => false,
                'html_element' => 'textarea',
            ],
            FormField::TYPE_EMAIL => [
                'name' => 'Email Input',
                'description' => 'Email address input with validation',
                'icon' => 'email',
                'category' => 'basic',
                'supports_placeholder' => true,
                'supports_default_value' => true,
                'supports_validation' => true,
                'supports_options' => false,
                'html_input_type' => 'email',
                'default_validation' => ['email' => true],
            ],
            FormField::TYPE_NUMBER => [
                'name' => 'Number Input',
                'description' => 'Numeric input field',
                'icon' => 'numbers',
                'category' => 'basic',
                'supports_placeholder' => true,
                'supports_default_value' => true,
                'supports_validation' => true,
                'supports_options' => false,
                'html_input_type' => 'number',
                'default_validation' => ['numeric' => true],
            ],
            FormField::TYPE_DATE => [
                'name' => 'Date Picker',
                'description' => 'Date selection field',
                'icon' => 'calendar-today',
                'category' => 'datetime',
                'supports_placeholder' => false,
                'supports_default_value' => true,
                'supports_validation' => true,
                'supports_options' => false,
                'html_input_type' => 'date',
            ],
            FormField::TYPE_TIME => [
                'name' => 'Time Picker',
                'description' => 'Time selection field',
                'icon' => 'access-time',
                'category' => 'datetime',
                'supports_placeholder' => false,
                'supports_default_value' => true,
                'supports_validation' => true,
                'supports_options' => false,
                'html_input_type' => 'time',
            ],
            FormField::TYPE_DATETIME => [
                'name' => 'Date & Time Picker',
                'description' => 'Date and time selection field',
                'icon' => 'event',
                'category' => 'datetime',
                'supports_placeholder' => false,
                'supports_default_value' => true,
                'supports_validation' => true,
                'supports_options' => false,
                'html_input_type' => 'datetime-local',
            ],
            FormField::TYPE_SELECT => [
                'name' => 'Select Dropdown',
                'description' => 'Dropdown selection field',
                'icon' => 'arrow-drop-down',
                'category' => 'choice',
                'supports_placeholder' => false,
                'supports_default_value' => true,
                'supports_validation' => true,
                'supports_options' => true,
                'html_element' => 'select',
                'requires_options' => true,
            ],
            FormField::TYPE_CHECKBOX => [
                'name' => 'Checkbox',
                'description' => 'Checkbox input for boolean or multiple values',
                'icon' => 'check-box',
                'category' => 'choice',
                'supports_placeholder' => false,
                'supports_default_value' => true,
                'supports_validation' => true,
                'supports_options' => true,
                'html_input_type' => 'checkbox',
                'supports_multiple' => true,
            ],
            FormField::TYPE_RADIO => [
                'name' => 'Radio Buttons',
                'description' => 'Radio button group for single selection',
                'icon' => 'radio-button-checked',
                'category' => 'choice',
                'supports_placeholder' => false,
                'supports_default_value' => true,
                'supports_validation' => true,
                'supports_options' => true,
                'html_input_type' => 'radio',
                'requires_options' => true,
            ],
            FormField::TYPE_FILE => [
                'name' => 'File Upload',
                'description' => 'File upload field',
                'icon' => 'attach-file',
                'category' => 'advanced',
                'supports_placeholder' => false,
                'supports_default_value' => false,
                'supports_validation' => true,
                'supports_options' => false,
                'html_input_type' => 'file',
                'default_validation' => ['file' => true],
            ],
            FormField::TYPE_HIDDEN => [
                'name' => 'Hidden Field',
                'description' => 'Hidden input field',
                'icon' => 'visibility-off',
                'category' => 'advanced',
                'supports_placeholder' => false,
                'supports_default_value' => true,
                'supports_validation' => false,
                'supports_options' => false,
                'html_input_type' => 'hidden',
            ],
        ];
    }

    /**
     * Get all available field types
     */
    public function getAvailableTypes(): array
    {
        return array_keys($this->fieldTypes);
    }

    /**
     * Get field type configuration
     */
    public function getTypeConfig(string $type): ?array
    {
        return $this->fieldTypes[$type] ?? null;
    }

    /**
     * Get all field types with their configurations
     */
    public function getAllTypes(): array
    {
        return $this->fieldTypes;
    }

    /**
     * Get field types grouped by category
     */
    public function getTypesByCategory(): array
    {
        $categories = [];
        
        foreach ($this->fieldTypes as $type => $config) {
            $category = $config['category'] ?? 'other';
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][$type] = $config;
        }
        
        return $categories;
    }

    /**
     * Get field types that support options (choices)
     */
    public function getTypesWithOptions(): array
    {
        return array_filter($this->fieldTypes, function($config) {
            return $config['supports_options'] ?? false;
        });
    }

    /**
     * Get field types that require options
     */
    public function getTypesRequiringOptions(): array
    {
        return array_filter($this->fieldTypes, function($config) {
            return $config['requires_options'] ?? false;
        });
    }

    /**
     * Check if a field type exists
     */
    public function hasType(string $type): bool
    {
        return isset($this->fieldTypes[$type]);
    }

    /**
     * Check if a field type supports a specific feature
     */
    public function typeSupports(string $type, string $feature): bool
    {
        $config = $this->getTypeConfig($type);
        return $config ? ($config["supports_{$feature}"] ?? false) : false;
    }

    /**
     * Get HTML input type for a field type
     */
    public function getHtmlInputType(string $type): string
    {
        $config = $this->getTypeConfig($type);
        return $config['html_input_type'] ?? 'text';
    }

    /**
     * Get HTML element type for a field type
     */
    public function getHtmlElement(string $type): string
    {
        $config = $this->getTypeConfig($type);
        return $config['html_element'] ?? 'input';
    }

    /**
     * Get default validation rules for a field type
     */
    public function getDefaultValidation(string $type): array
    {
        $config = $this->getTypeConfig($type);
        return $config['default_validation'] ?? [];
    }

    /**
     * Get field type display name
     */
    public function getTypeName(string $type): string
    {
        $config = $this->getTypeConfig($type);
        return $config['name'] ?? $type;
    }

    /**
     * Get field type description
     */
    public function getTypeDescription(string $type): string
    {
        $config = $this->getTypeConfig($type);
        return $config['description'] ?? '';
    }

    /**
     * Get field type icon
     */
    public function getTypeIcon(string $type): string
    {
        $config = $this->getTypeConfig($type);
        return $config['icon'] ?? 'help';
    }

    /**
     * Validate field configuration against type requirements
     */
    public function validateFieldConfig(string $type, array $config): array
    {
        $errors = [];
        $typeConfig = $this->getTypeConfig($type);
        
        if (!$typeConfig) {
            $errors[] = "Unknown field type: {$type}";
            return $errors;
        }

        // Check if options are required but missing
        if (($typeConfig['requires_options'] ?? false) && empty($config['options']['choices'])) {
            $errors[] = "Field type '{$type}' requires options to be defined";
        }

        // Check if default value is supported
        if (!($typeConfig['supports_default_value'] ?? true) && !empty($config['defaultValue'])) {
            $errors[] = "Field type '{$type}' does not support default values";
        }

        // Check if placeholder is supported
        if (!($typeConfig['supports_placeholder'] ?? true) && !empty($config['placeholder'])) {
            $errors[] = "Field type '{$type}' does not support placeholders";
        }

        return $errors;
    }

    /**
     * Get recommended field types for common use cases
     */
    public function getRecommendedTypes(): array
    {
        return [
            'contact_form' => [
                FormField::TYPE_TEXT,
                FormField::TYPE_EMAIL,
                FormField::TYPE_TEXTAREA,
                FormField::TYPE_SELECT,
            ],
            'survey' => [
                FormField::TYPE_RADIO,
                FormField::TYPE_CHECKBOX,
                FormField::TYPE_SELECT,
                FormField::TYPE_TEXTAREA,
            ],
            'registration' => [
                FormField::TYPE_TEXT,
                FormField::TYPE_EMAIL,
                FormField::TYPE_DATE,
                FormField::TYPE_SELECT,
                FormField::TYPE_CHECKBOX,
            ],
            'feedback' => [
                FormField::TYPE_TEXTAREA,
                FormField::TYPE_RADIO,
                FormField::TYPE_SELECT,
                FormField::TYPE_NUMBER,
            ],
        ];
    }

    /**
     * Get field type categories with display names
     */
    public function getCategoryNames(): array
    {
        return [
            'basic' => 'Basic Fields',
            'choice' => 'Choice Fields',
            'datetime' => 'Date & Time Fields',
            'advanced' => 'Advanced Fields',
            'other' => 'Other Fields',
        ];
    }

    /**
     * Register a custom field type
     */
    public function registerType(string $type, array $config): void
    {
        $this->fieldTypes[$type] = $config;
    }

    /**
     * Unregister a field type
     */
    public function unregisterType(string $type): void
    {
        unset($this->fieldTypes[$type]);
    }
}