<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\FormRepository')]
#[ORM\Table(name: 'forms')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['slug'], message: 'This form slug is already in use')]
class Form
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    #[Assert\Regex(pattern: '/^[a-z0-9-_]+$/', message: 'Slug must contain only lowercase letters, numbers, hyphens, and underscores')]
    private ?string $slug = null;

    #[ORM\Column(name: '`schema`', type: 'json')]
    #[Assert\NotNull]
    private array $schema = [];

    #[ORM\Column(type: 'json')]
    private array $tags = [];

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $assignedTo = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creator = null;

    #[ORM\OneToMany(targetEntity: FormField::class, mappedBy: 'form', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['sortOrder' => 'ASC'])]
    private Collection $fields;

    public function __construct()
    {
        $this->fields = new ArrayCollection();
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = strtolower($slug);
        return $this;
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function setSchema(array $schema): static
    {
        $this->schema = $schema;
        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }

    public function addTag(string $tag): static
    {
        if (!in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }
        return $this;
    }

    public function removeTag(string $tag): static
    {
        $key = array_search($tag, $this->tags, true);
        if ($key !== false) {
            unset($this->tags[$key]);
            $this->tags = array_values($this->tags); // Re-index array
        }
        return $this;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getAssignedTo(): ?string
    {
        return $this->assignedTo;
    }

    public function setAssignedTo(?string $assignedTo): static
    {
        $this->assignedTo = $assignedTo;
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

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): static
    {
        $this->creator = $creator;
        return $this;
    }

    /**
     * @return Collection<int, FormField>
     */
    public function getFields(): Collection
    {
        return $this->fields;
    }

    public function addField(FormField $field): static
    {
        if (!$this->fields->contains($field)) {
            $this->fields->add($field);
            $field->setForm($this);
        }

        return $this;
    }

    public function removeField(FormField $field): static
    {
        if ($this->fields->removeElement($field)) {
            // set the owning side to null (unless already changed)
            if ($field->getForm() === $this) {
                $field->setForm(null);
            }
        }

        return $this;
    }

    /**
     * Get fields ordered by sort order
     */
    public function getOrderedFields(): array
    {
        $fields = $this->fields->toArray();
        usort($fields, function(FormField $a, FormField $b) {
            return $a->getSortOrder() <=> $b->getSortOrder();
        });
        return $fields;
    }

    /**
     * Generate slug from name if not set
     */
    public function generateSlug(): static
    {
        if (!$this->slug && $this->name) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $this->name)));
            $this->setSlug($slug);
        }
        return $this;
    }

    /**
     * Validate the form schema structure
     */
    public function isValidSchema(): bool
    {
        if (empty($this->schema)) {
            return false;
        }

        // Basic schema validation - must have fields array
        if (!isset($this->schema['fields']) || !is_array($this->schema['fields'])) {
            return false;
        }

        // Validate each field in schema
        foreach ($this->schema['fields'] as $field) {
            if (!is_array($field) || !isset($field['type']) || !isset($field['name'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get schema version for compatibility tracking
     */
    public function getSchemaVersion(): string
    {
        return $this->schema['version'] ?? '1.0';
    }

    /**
     * Set schema version
     */
    public function setSchemaVersion(string $version): static
    {
        $this->schema['version'] = $version;
        return $this;
    }

    /**
     * Get form configuration from schema
     */
    public function getFormConfig(): array
    {
        return $this->schema['config'] ?? [];
    }

    /**
     * Set form configuration in schema
     */
    public function setFormConfig(array $config): static
    {
        $this->schema['config'] = $config;
        return $this;
    }

    /**
     * Get field definitions from schema
     */
    public function getFieldDefinitions(): array
    {
        return $this->schema['fields'] ?? [];
    }

    /**
     * Set field definitions in schema
     */
    public function setFieldDefinitions(array $fields): static
    {
        $this->schema['fields'] = $fields;
        return $this;
    }

    /**
     * Add field definition to schema
     */
    public function addFieldDefinition(array $fieldDef): static
    {
        if (!isset($this->schema['fields'])) {
            $this->schema['fields'] = [];
        }
        $this->schema['fields'][] = $fieldDef;
        return $this;
    }

    /**
     * Remove field definition from schema by name
     */
    public function removeFieldDefinition(string $fieldName): static
    {
        if (isset($this->schema['fields'])) {
            $this->schema['fields'] = array_filter(
                $this->schema['fields'],
                fn($field) => ($field['name'] ?? '') !== $fieldName
            );
            $this->schema['fields'] = array_values($this->schema['fields']); // Re-index
        }
        return $this;
    }

    /**
     * Check if form has a specific field type
     */
    public function hasFieldType(string $type): bool
    {
        foreach ($this->getFieldDefinitions() as $field) {
            if (($field['type'] ?? '') === $type) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get count of fields in the form
     */
    public function getFieldCount(): int
    {
        return count($this->getFieldDefinitions());
    }

    public function __toString(): string
    {
        return $this->name ?? 'Untitled Form';
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}