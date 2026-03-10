<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\EventTagRepository')]
#[ORM\Table(name: 'event_tags')]
#[UniqueEntity(fields: ['name'], message: 'This tag name is already in use')]
class EventTag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 100)]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9\s\-_]+$/', message: 'Tag name can only contain letters, numbers, spaces, hyphens, and underscores')]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Color must be a valid hex color code (e.g., #FF0000)')]
    private ?string $color = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToMany(targetEntity: Event::class, mappedBy: 'tags')]
    private Collection $events;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->createdAt = new \DateTime();
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
        $this->name = trim($name);
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        if ($color) {
            // Ensure color starts with # and is uppercase
            if (!str_starts_with($color, '#')) {
                $color = '#' . $color;
            }
            $this->color = strtoupper($color);
        } else {
            $this->color = null;
        }
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Collection<int, Event>
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(Event $event): static
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->addTag($this);
        }

        return $this;
    }

    public function removeEvent(Event $event): static
    {
        if ($this->events->removeElement($event)) {
            $event->removeTag($this);
        }

        return $this;
    }

    /**
     * Get the number of events using this tag
     */
    public function getEventCount(): int
    {
        return $this->events->count();
    }

    /**
     * Get the slug version of the tag name
     */
    public function getSlug(): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $this->name ?? ''));
    }

    public function __toString(): string
    {
        return $this->name ?? 'Unnamed Tag';
    }
}