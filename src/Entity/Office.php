<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\OfficeRepository')]
#[ORM\Table(name: 'offices')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['code'], message: 'This office code is already in use')]
class Office
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 20, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 20)]
    #[Assert\Regex(pattern: '/^[A-Z0-9_-]+$/', message: 'Office code must contain only uppercase letters, numbers, underscores, and hyphens')]
    private ?string $code = null;

    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Color must be a valid hex color code (e.g., #FF0000)')]
    private ?string $color = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true)]
    private ?self $parent = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'office')]
    private Collection $users;

    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'office')]
    private Collection $events;

    #[ORM\OneToMany(targetEntity: DirectoryContact::class, mappedBy: 'office')]
    private Collection $directoryContacts;

    #[ORM\ManyToOne(targetEntity: OfficeCluster::class, inversedBy: 'offices')]
    #[ORM\JoinColumn(nullable: true)]
    private ?OfficeCluster $cluster = null;

    #[ORM\OneToMany(targetEntity: Division::class, mappedBy: 'office', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['displayOrder' => 'ASC', 'name' => 'ASC'])]
    private Collection $divisions;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->users = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->directoryContacts = new ArrayCollection();
        $this->divisions = new ArrayCollection();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);
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

    /**
     * Get the effective color - from cluster if available, otherwise own color or default
     */
    public function getEffectiveColor(): string
    {
        // If office has a cluster, use cluster's color
        if ($this->cluster && $this->cluster->getColor()) {
            return $this->cluster->getColor();
        }
        
        // Otherwise use office's own color or default
        return $this->color ?? '#3B82F6';
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child)) {
            // set the owning side to null (unless already changed)
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setOffice($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {
            if ($user->getOffice() === $this) {
                $user->setOffice(null);
            }
        }

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
            $event->setOffice($this);
        }

        return $this;
    }

    public function removeEvent(Event $event): static
    {
        if ($this->events->removeElement($event)) {
            if ($event->getOffice() === $this) {
                $event->setOffice(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, DirectoryContact>
     */
    public function getDirectoryContacts(): Collection
    {
        return $this->directoryContacts;
    }

    public function addDirectoryContact(DirectoryContact $directoryContact): static
    {
        if (!$this->directoryContacts->contains($directoryContact)) {
            $this->directoryContacts->add($directoryContact);
            $directoryContact->setOffice($this);
        }

        return $this;
    }

    public function removeDirectoryContact(DirectoryContact $directoryContact): static
    {
        if ($this->directoryContacts->removeElement($directoryContact)) {
            if ($directoryContact->getOffice() === $this) {
                $directoryContact->setOffice(null);
            }
        }

        return $this;
    }

    /**
     * Get the full hierarchical name of the office
     */
    public function getFullName(): string
    {
        $names = [];
        $current = $this;
        
        while ($current !== null) {
            array_unshift($names, $current->getName());
            $current = $current->getParent();
        }
        
        return implode(' > ', $names);
    }

    /**
     * Get all descendant offices (children, grandchildren, etc.)
     */
    public function getAllDescendants(): array
    {
        $descendants = [];
        
        foreach ($this->children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->getAllDescendants());
        }
        
        return $descendants;
    }

    /**
     * Get all ancestor offices (parent, grandparent, etc.)
     */
    public function getAllAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;
        
        while ($current !== null) {
            $ancestors[] = $current;
            $current = $current->getParent();
        }
        
        return $ancestors;
    }

    /**
     * Check if this office is a descendant of another office
     */
    public function isDescendantOf(self $office): bool
    {
        $current = $this->parent;
        
        while ($current !== null) {
            if ($current === $office) {
                return true;
            }
            $current = $current->getParent();
        }
        
        return false;
    }

    /**
     * Check if this office is an ancestor of another office
     */
    public function isAncestorOf(self $office): bool
    {
        return $office->isDescendantOf($this);
    }

    /**
     * Get the root office (top-level parent)
     */
    public function getRoot(): self
    {
        $current = $this;
        
        while ($current->getParent() !== null) {
            $current = $current->getParent();
        }
        
        return $current;
    }

    /**
     * Get the depth level in the hierarchy (0 for root)
     */
    public function getDepthLevel(): int
    {
        $level = 0;
        $current = $this->parent;
        
        while ($current !== null) {
            $level++;
            $current = $current->getParent();
        }
        
        return $level;
    }

    /**
     * Check if office has children
     */
    public function hasChildren(): bool
    {
        return !$this->children->isEmpty();
    }

    /**
     * Check if office is root (has no parent)
     */
    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    /**
     * Check if office is leaf (has no children)
     */
    public function isLeaf(): bool
    {
        return $this->children->isEmpty();
    }

    public function getCluster(): ?OfficeCluster
    {
        return $this->cluster;
    }

    public function setCluster(?OfficeCluster $cluster): static
    {
        $this->cluster = $cluster;
        return $this;
    }

    /**
     * @return Collection<int, Division>
     */
    public function getDivisions(): Collection
    {
        return $this->divisions;
    }

    public function addDivision(Division $division): static
    {
        if (!$this->divisions->contains($division)) {
            $this->divisions->add($division);
            $division->setOffice($this);
        }

        return $this;
    }

    public function removeDivision(Division $division): static
    {
        if ($this->divisions->removeElement($division)) {
            if ($division->getOffice() === $this) {
                $division->setOffice(null);
            }
        }

        return $this;
    }

    public function getDivisionCount(): int
    {
        return $this->divisions->count();
    }

    public function __toString(): string
    {
        return $this->name ?? 'Unnamed Office';
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}