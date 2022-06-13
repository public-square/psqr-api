<?php

namespace PublicSquare\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use PublicSquare\Repository\AggregationRepository;

#[ORM\Entity(repositoryClass: AggregationRepository::class)]
class Aggregation
{
    public const FEED_TYPE = 1;
    public const LIST_TYPE = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'smallint')]
    private ?int $type = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $name = null;

    #[ORM\OneToMany(targetEntity: Permission::class, mappedBy: 'aggregation')]
    private array | \Doctrine\Common\Collections\ArrayCollection | \Doctrine\Common\Collections\Collection $grants;

    public function __construct()
    {
        $this->grants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getGrants(): array
    {
        return $this->grants;
    }

    public function addGrant(Permission $grant): self
    {
        if (!$this->grants->contains($grant)) {
            $this->grants[] = $grant;
            $grant->setAggregation($this);
        }

        return $this;
    }

    public function removeGrant(Permission $grant): self
    {
        if ($this->grants->removeElement($grant)) {
            // set the owning side to null (unless already changed)
            if ($grant->getAggregation() === $this) {
                $grant->setAggregation(null);
            }
        }

        return $this;
    }
}
