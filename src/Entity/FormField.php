<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\FormFieldRepository')]
#[ORM\Table(name: 'form_fields')]
#[ORM\HasLifecycleCallbacks]
class FormField
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_EMAIL = 'email';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_TIME = 'time';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_SELECT = 'select';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_RADIO = 'radio';
    public const TYPE_FILE = 'file';
    public const TYPE_HIDDEN = 'hidden';

    public const AVAILABLE_TYPES = [
        self::TYPE_TEXT,
        self::TYPE_TEXTAREA,
        self::TYPE_EMAIL,
        self::TYPE_NUMBER,
        self::TYPE_DATE,
        self::TYPE_TIME,
        self::TYPE_DATETIME,
        self::TYPE_SELECT,
        self::TYPE_CHECKBOX,
        self::TYPE_RADIO,
        self::TYPE_FILE,
        self::TYPE_HIDDEN,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $label = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: self::AVAILABLE_TYPES)]
    private ?string $type = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $placeholder = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $defaultValue = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRequired = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer')]
    #[Assert\PositiveOrZero]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'json')]
    private array $options = [];

    #[ORM\Column(type: 'json')]
    private array $validationRules = [];

    #[ORM\Column(type: 'json')]
    private array $attributes = [];

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Form::class, inversedBy: 'fields')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Form $form = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        if (!in_array($type, self::AVAILABLE_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid field type "%s". Available types: %s', $type, implode(', ', self::AVAILABLE_TYPES)));
        }
        $this->type = $type;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function setPlaceholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(?string $defaultValue): static
    {
        $this->defaultValue = $defaultValue;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setRequired(bool $isRequired): static
    {
        $this->isRequired = $isRequired;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = max(0, $sortOrder);
        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): static
    {
        $this->options = $options;
        return $this;
    }

    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function setOption(string $key, mixed $value): static
    {
        $this->options[$key] = $value;
        return $this;
    }

    public function hasOption(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }

    public function removeOption(string $key): static
    {
        unset($this->options[$key]);
        return $this;
    }

    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    public function setValidationRules(array $validationRules): static
    {
        $this->validationRules = $validationRules;
        return $this;
    }

    public function addValidationRule(string $rule, mixed $value = true): static
    {
        $this->validationRules[$rule] = $value;
        return $this;
    }

    public function removeValidationRule(string $rule): static
    {
        unset($this->validationRules[$rule]);
        return $this;
    }

    public function hasValidationRule(string $rule): bool
    {
        return array_key_exists($rule, $this->validationRules);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): static
    {
        $this->attributes = $attributes;
        return $this;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function removeAttribute(string $key): static
    {
        unset($this->attributes[$key]);
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getForm(): ?Form
    {
        return $this->form;
    }

    public function setForm(?Form $form): static
    {
        $this->form = $form;
        return $this;
    }

    /**
     * Get available field types
     */
    public static function getAvailableTypes(): array
    {
        return self::AVAILABLE_TYPES;
    }

    /**
     * Get field type display names
     */
    public static function getTypeDisplayNames(): array
    {
        return [
            self::TYPE_TEXT => 'Text Input',
            self::TYPE_TEXTAREA => 'Textarea',
            self::TYPE_EMAIL => 'Email Input',
            self::TYPE_NUMBER => 'Number Input',
            self::TYPE_DATE => 'Date Picker',
            self::TYPE_TIME => 'Time Picker',
            self::TYPE_DATETIME => 'Date & Time Picker',
            self::TYPE_SELECT => 'Select Dropdown',
            self::TYPE_CHECKBOX => 'Checkbox',
            self::TYPE_RADIO => 'Radio Buttons',
            self::TYPE_FILE => 'File Upload',
            self::TYPE_HIDDEN => 'Hidden Field',
        ];
    }

    /**
     * Get display name for current field type
     */
    public function getTypeDisplayName(): string
    {
        $displayNames = self::getTypeDisplayNames();
        return $displayNames[$this->type] ?? $this->type;
    }

    /**
     * Check if field type supports options (select, radio, checkbox)
     */
    public function supportsOptions(): bool
    {
        return in_array($this->type, [self::TYPE_SELECT, self::TYPE_RADIO, self::TYPE_CHECKBOX], true);
    }

    /**
     * Check if field type supports multiple values
     */
    public function supportsMultipleValues(): bool
    {
        return $this->type === self::TYPE_CHECKBOX && $this->getOption('multiple', false);
    }

    /**
     * Get field options for select/radio/checkbox fields
     */
    public function getFieldOptions(): array
    {
        if (!$this->supportsOptions()) {
            return [];
        }

        return $this->getOption('choices', []);
    }

    /**
     * Set field options for select/radio/checkbox fields
     */
    public function setFieldOptions(array $choices): static
    {
        if ($this->supportsOptions()) {
            $this->setOption('choices', $choices);
        }
        return $this;
    }

    /**
     * Add a choice option for select/radio/checkbox fields
     */
    public function addFieldOption(string $value, string $label): static
    {
        if ($this->supportsOptions()) {
            $choices = $this->getFieldOptions();
            $choices[$value] = $label;
            $this->setFieldOptions($choices);
        }
        return $this;
    }

    /**
     * Remove a choice option
     */
    public function removeFieldOption(string $value): static
    {
        if ($this->supportsOptions()) {
            $choices = $this->getFieldOptions();
            unset($choices[$value]);
            $this->setFieldOptions($choices);
        }
        return $this;
    }

    /**
     * Get HTML input type for rendering
     */
    public function getHtmlInputType(): string
    {
        return match($this->type) {
            self::TYPE_EMAIL => 'email',
            self::TYPE_NUMBER => 'number',
            self::TYPE_DATE => 'date',
            self::TYPE_TIME => 'time',
            self::TYPE_DATETIME => 'datetime-local',
            self::TYPE_FILE => 'file',
            self::TYPE_HIDDEN => 'hidden',
            default => 'text'
        };
    }

    /**
     * Check if field should be rendered as a specific HTML element
     */
    public function isTextarea(): bool
    {
        return $this->type === self::TYPE_TEXTAREA;
    }

    public function isSelect(): bool
    {
        return $this->type === self::TYPE_SELECT;
    }

    public function isCheckbox(): bool
    {
        return $this->type === self::TYPE_CHECKBOX;
    }

    public function isRadio(): bool
    {
        return $this->type === self::TYPE_RADIO;
    }

    public function isFile(): bool
    {
        return $this->type === self::TYPE_FILE;
    }

    public function isHidden(): bool
    {
        return $this->type === self::TYPE_HIDDEN;
    }

    /**
     * Generate field configuration array for form rendering
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'description' => $this->description,
            'placeholder' => $this->placeholder,
            'defaultValue' => $this->defaultValue,
            'isRequired' => $this->isRequired,
            'isActive' => $this->isActive,
            'sortOrder' => $this->sortOrder,
            'options' => $this->options,
            'validationRules' => $this->validationRules,
            'attributes' => $this->attributes,
        ];
    }

    public function __toString(): string
    {
        return $this->label ?? $this->name ?? 'Untitled Field';
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}