<?php

namespace App\Entity;

use App\Entity\Trait\DateInterface;
use App\Entity\Trait\DateTrait;
use App\Repository\ContactRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
class Contact implements DateInterface
{
    use DateTrait;
    public const TYPE_REPLY = 'reply';
    public const TYPE_CONTACT = 'contact';
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(length: 100)]
    private string $email;

    #[ORM\Column]
    private bool $isView = false;

    #[ORM\Column]
    private int $origin;

    #[ORM\Column(length: 10)]
    private string $type;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function isView(): ?bool
    {
        return $this->isView;
    }

    public function setIsView(bool $isView): static
    {
        $this->isView = $isView;

        return $this;
    }

    public function getOrigin(): ?int
    {
        return $this->origin;
    }

    public function setOrigin(int $origin): static
    {
        $this->origin = $origin;

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
}
