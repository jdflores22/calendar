<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\EventRepository')]
#[ORM\Table(name: 'events')]
#[ORM\HasLifecycleCallbacks]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotNull]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotNull]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $location = null;

    #[ORM\Column(type: 'string', length: 7)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Color must be a valid hex color code (e.g., #FF0000)')]
    private ?string $color = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRecurring = false;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $recurrencePattern = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isAllDay = false;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['low', 'normal', 'high', 'urgent'])]
    private ?string $priority = 'normal';

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['confirmed', 'tentative', 'cancelled'])]
    private ?string $status = 'confirmed';

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Assert\Choice(choices: ['in-person', 'zoom', 'hybrid', 'other'])]
    private ?string $meetingType = 'in-person';

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Assert\Url(message: 'Please enter a valid URL')]
    private ?string $zoomLink = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creator = null;

    #[ORM\ManyToOne(targetEntity: Office::class, inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Office $office = null;

    #[ORM\ManyToMany(targetEntity: Office::class)]
    #[ORM\JoinTable(name: 'event_offices')]
    private Collection $taggedOffices;

    #[ORM\ManyToMany(targetEntity: EventTag::class, inversedBy: 'events')]
    #[ORM\JoinTable(name: 'event_event_tags')]
    private Collection $tags;

    #[ORM\OneToMany(targetEntity: EventAttachment::class, mappedBy: 'event', cascade: ['persist', 'remove'])]
    private Collection $attachments;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->taggedOffices = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): static
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): static
    {
        $this->endTime = $endTime;
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

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
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

    public function isAllDay(): bool
    {
        return $this->isAllDay;
    }

    public function setAllDay(bool $isAllDay): static
    {
        $this->isAllDay = $isAllDay;
        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(?string $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getMeetingType(): ?string
    {
        return $this->meetingType;
    }

    public function setMeetingType(?string $meetingType): static
    {
        $this->meetingType = $meetingType;
        return $this;
    }

    public function getZoomLink(): ?string
    {
        return $this->zoomLink;
    }

    public function setZoomLink(?string $zoomLink): static
    {
        $this->zoomLink = $zoomLink;
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

    public function getOffice(): ?Office
    {
        return $this->office;
    }

    public function setOffice(?Office $office): static
    {
        $this->office = $office;
        return $this;
    }

    /**
     * @return Collection<int, EventTag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(EventTag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(EventTag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    /**
     * @return Collection<int, Office>
     */
    public function getTaggedOffices(): Collection
    {
        return $this->taggedOffices;
    }

    public function addTaggedOffice(Office $office): static
    {
        if (!$this->taggedOffices->contains($office)) {
            $this->taggedOffices->add($office);
        }

        return $this;
    }

    public function removeTaggedOffice(Office $office): static
    {
        $this->taggedOffices->removeElement($office);
        return $this;
    }

    /**
     * Clear all tagged offices
     */
    public function clearTaggedOffices(): static
    {
        $this->taggedOffices->clear();
        return $this;
    }

    /**
     * Set tagged offices from an array of office IDs or Office objects
     */
    public function setTaggedOffices(array $offices): static
    {
        $this->taggedOffices->clear();
        
        foreach ($offices as $office) {
            if ($office instanceof Office) {
                $this->addTaggedOffice($office);
            }
        }
        
        return $this;
    }

    /**
     * Check if an office is tagged for this event
     */
    public function hasTaggedOffice(Office $office): bool
    {
        return $this->taggedOffices->contains($office);
    }

    /**
     * Get all tagged office names as an array
     */
    public function getTaggedOfficeNames(): array
    {
        return $this->taggedOffices->map(function(Office $office) {
            return $office->getName();
        })->toArray();
    }

    /**
     * @return Collection<int, EventAttachment>
     */
    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(EventAttachment $attachment): static
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
            $attachment->setEvent($this);
        }

        return $this;
    }

    public function removeAttachment(EventAttachment $attachment): static
    {
        if ($this->attachments->removeElement($attachment)) {
            // set the owning side to null (unless already changed)
            if ($attachment->getEvent() === $this) {
                $attachment->setEvent(null);
            }
        }

        return $this;
    }

    /**
     * Get the duration of the event in minutes
     */
    public function getDurationInMinutes(): int
    {
        if (!$this->startTime || !$this->endTime) {
            return 0;
        }

        $diff = $this->endTime->diff($this->startTime);
        return ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    }

    /**
     * Get the duration of the event as a formatted string
     */
    public function getDurationFormatted(): string
    {
        $minutes = $this->getDurationInMinutes();
        
        if ($minutes < 60) {
            return $minutes . ' minutes';
        }
        
        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes === 0) {
            return $hours . ' hour' . ($hours > 1 ? 's' : '');
        }
        
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ' . $remainingMinutes . ' minutes';
    }

    /**
     * Check if the event conflicts with another event
     */
    public function conflictsWith(self $other): bool
    {
        if (!$this->startTime || !$this->endTime || !$other->getStartTime() || !$other->getEndTime()) {
            return false;
        }

        return $this->startTime < $other->getEndTime() && $this->endTime > $other->getStartTime();
    }

    /**
     * Check if the event is currently happening
     */
    public function isHappening(): bool
    {
        $now = new \DateTime();
        return $this->startTime <= $now && $this->endTime >= $now;
    }

    /**
     * Check if the event is in the past
     */
    public function isPast(): bool
    {
        $now = new \DateTime();
        return $this->endTime < $now;
    }

    /**
     * Check if the event is in the future
     */
    public function isFuture(): bool
    {
        $now = new \DateTime();
        return $this->startTime > $now;
    }

    /**
     * Check if the event is today
     */
    public function isToday(): bool
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');
        
        return $this->startTime >= $today && $this->startTime < $tomorrow;
    }

    /**
     * Get the event color, falling back to office color if not set
     */
    public function getEffectiveColor(): string
    {
        if ($this->color) {
            return $this->color;
        }
        
        // If there's a primary office, use its color
        if ($this->office && $this->office->getColor()) {
            return $this->office->getColor();
        }
        
        // If there are tagged offices, use the first one's color
        if (!$this->taggedOffices->isEmpty()) {
            $firstTaggedOffice = $this->taggedOffices->first();
            if ($firstTaggedOffice && $firstTaggedOffice->getColor()) {
                return $firstTaggedOffice->getColor();
            }
        }
        
        return '#007BFF'; // Default blue color
    }

    /**
     * Get a short description for display
     */
    public function getShortDescription(int $maxLength = 100): string
    {
        if (!$this->description) {
            return '';
        }
        
        if (strlen($this->description) <= $maxLength) {
            return $this->description;
        }
        
        return substr($this->description, 0, $maxLength - 3) . '...';
    }

    /**
     * Get all tag names as an array
     */
    public function getTagNames(): array
    {
        return $this->tags->map(function(EventTag $tag) {
            return $tag->getName();
        })->toArray();
    }

    /**
     * Check if event has a specific tag
     */
    public function hasTag(string $tagName): bool
    {
        return in_array($tagName, $this->getTagNames(), true);
    }

    /**
     * Validate that end time is after start time
     */
    public function isValidTimeRange(): bool
    {
        if (!$this->startTime || !$this->endTime) {
            return false;
        }
        
        return $this->endTime > $this->startTime;
    }

    public function __toString(): string
    {
        return $this->title ?? 'Untitled Event';
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}