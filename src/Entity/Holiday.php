<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\HolidayRepository')]
#[ORM\Table(name: 'holidays')]
#[ORM\HasLifecycleCallbacks]
class Holiday
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['national', 'regional', 'local', 'observance'])]
    private ?string $type = 'national';

    #[ORM\Column(type: 'string', length: 7)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Color must be a valid hex color code (e.g., #FF0000)')]
    private ?string $color = '#FF6B6B';

    #[ORM\Column(type: 'boolean')]
    private bool $isRecurring = false;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $recurrencePattern = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1900, max: 2100)]
    private ?int $year = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $country = 'Philippines';

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $region = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        // Ensure color starts with # and is uppercase
        if (!str_starts_with($color, '#')) {
            $color = '#' . $color;
        }
        $this->color = strtoupper($color);
        return $this;
    }

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function setRecurring(bool $isRecurring): static
    {
        $this->isRecurring = $isRecurring;
        return $this;
    }

    public function getRecurrencePattern(): ?array
    {
        return $this->recurrencePattern;
    }

    public function setRecurrencePattern(?array $recurrencePattern): static
    {
        $this->recurrencePattern = $recurrencePattern;
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

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;
        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): static
    {
        $this->region = $region;
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

    /**
     * Check if the holiday is today
     */
    public function isToday(): bool
    {
        $today = new \DateTime('today');
        return $this->date && $this->date->format('Y-m-d') === $today->format('Y-m-d');
    }

    /**
     * Check if the holiday is in a specific year
     */
    public function isInYear(int $year): bool
    {
        if ($this->year && $this->year !== $year) {
            return false;
        }
        
        return $this->date && (int)$this->date->format('Y') === $year;
    }

    /**
     * Get the holiday for a specific year (for recurring holidays)
     */
    public function getDateForYear(int $year): ?\DateTimeInterface
    {
        if (!$this->date) {
            return null;
        }

        if ($this->isRecurring) {
            // For recurring holidays, create a new date with the same month/day but different year
            $newDate = clone $this->date;
            $newDate->setDate($year, (int)$this->date->format('n'), (int)$this->date->format('j'));
            return $newDate;
        }

        // For non-recurring holidays, only return if it's the correct year
        if ($this->isInYear($year)) {
            return $this->date;
        }

        return null;
    }

    /**
     * Get formatted date string
     */
    public function getFormattedDate(): string
    {
        if (!$this->date) {
            return '';
        }

        return $this->date->format('F j, Y');
    }

    /**
     * Get short formatted date string
     */
    public function getShortFormattedDate(): string
    {
        if (!$this->date) {
            return '';
        }

        return $this->date->format('M j');
    }

    /**
     * Get type display name
     */
    public function getTypeDisplayName(): string
    {
        return match($this->type) {
            'national' => 'National Holiday',
            'regional' => 'Regional Holiday',
            'local' => 'Local Holiday',
            'observance' => 'Observance',
            default => ucfirst($this->type)
        };
    }

    /**
     * Check if holiday conflicts with a date range
     */
    public function conflictsWithDateRange(\DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        if (!$this->date) {
            return false;
        }

        return $this->date >= $start && $this->date <= $end;
    }

    public function __toString(): string
    {
        return $this->name ?? 'Untitled Holiday';
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}