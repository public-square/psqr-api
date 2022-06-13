<?php

namespace PublicSquare\Entity;

use Doctrine\ORM\Mapping as ORM;
use PublicSquare\Repository\PermissionRepository;

#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\Table(name: 'permission')]
class Permission
{
    public const GRANT_PUBLISH = 1;
    public const GRANT_CURATE  = 2;
    public const GRANT_ADMIN   = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Aggregation::class, inversedBy: 'grants')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?\PublicSquare\Entity\Aggregation $aggregation = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $network = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $publisher = null;

    #[ORM\Column(type: 'smallint')]
    private ?int $type = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $did = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $kid = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAggregation(): ?Aggregation
    {
        return $this->aggregation;
    }

    public function setAggregation(?Aggregation $aggregation): self
    {
        $this->aggregation = $aggregation;

        return $this;
    }

    public function getNetwork(): ?bool
    {
        return $this->network;
    }

    public function setNetwork(?bool $network): self
    {
        $this->network = $network;

        return $this;
    }

    public function getPublisher(): ?string
    {
        return $this->publisher;
    }

    public function setPublisher(?string $publisher): self
    {
        $this->publisher = $publisher;

        return $this;
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

    public function getDid(): ?string
    {
        return $this->did;
    }

    public function setDid(string $did): self
    {
        $this->did = $did;

        return $this;
    }

    public function getKid(): ?string
    {
        return $this->kid;
    }

    public function setKid(?string $kid): self
    {
        $this->kid = $kid;

        return $this;
    }
}
