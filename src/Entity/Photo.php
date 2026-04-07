<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[ORM\Entity(repositoryClass: "App\Repository\PhotoRepository")]
class Photo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private ?int $id = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $filename = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $originalName = null;

    #[ORM\Column(type: "string", length: 50)]
    private ?string $mimeType = null;

    #[ORM\Column(type: "integer")]
    private ?int $size = null;

    #[ORM\Column(type: "string", length: 255)]
    private ?string $path = null;

    #[ORM\Column(type: "string", nullable: true)]
    private ?string $prestationReference = null;

    #[ORM\Column(type: "string", nullable: true)]
    private ?string $internalOrder = null;

    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $interventionId = null;

    // Nouveau champ : date de prise
    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $dateTaken = null;

    // Nouveau champ : lieu
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $location = null;

    // Nouveau champ : appareil photo
    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $cameraModel = null;

    // Metadata JSON pour infos optionnelles
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    private ?UploadedFile $imageFile = null;

    public function getImageFile(): ?UploadedFile
    {
        return $this->imageFile;
    }

    public function setImageFile(?UploadedFile $imageFile): self
    {
        $this->imageFile = $imageFile;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getPrestationReference(): ?string
    {
        return $this->prestationReference;
    }

    public function setPrestationReference(?string $prestationReference): static
    {
        $this->prestationReference = $prestationReference;

        return $this;
    }

    public function getInternalOrder(): ?string
    {
        return $this->internalOrder;
    }

    public function setInternalOrder(?string $internalOrder): static
    {
        $this->internalOrder = $internalOrder;

        return $this;
    }

    public function getInterventionId(): ?int
    {
        return $this->interventionId;
    }

    public function setInterventionId(?int $interventionId): static
    {
        $this->interventionId = $interventionId;

        return $this;
    }

    public function getDateTaken(): ?\DateTimeInterface
    {
        return $this->dateTaken;
    }

    public function setDateTaken(?\DateTimeInterface $dateTaken): static
    {
        $this->dateTaken = $dateTaken;

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

    public function getCameraModel(): ?string
    {
        return $this->cameraModel;
    }

    public function setCameraModel(?string $cameraModel): static
    {
        $this->cameraModel = $cameraModel;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
