<?php

namespace App\Entity;

use App\Repository\ThemeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ThemeRepository::class)]
class Theme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dateStart = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dateEnd = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?User $winner = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?SurveyChoice $ThemeChoice = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateStart(): ?\DateTime
    {
        return $this->dateStart;
    }

    public function setDateStart(\DateTime $dateStart): static
    {
        $this->dateStart = $dateStart;

        return $this;
    }

    public function getDateEnd(): ?\DateTime
    {
        return $this->dateEnd;
    }

    public function setDateEnd(\DateTime $dateEnd): static
    {
        $this->dateEnd = $dateEnd;

        return $this;
    }

    public function getWinner(): ?User
    {
        return $this->winner;
    }

    public function setWinner(?User $winner): static
    {
        $this->winner = $winner;

        return $this;
    }

    public function getThemeChoice(): ?SurveyChoice
    {
        return $this->ThemeChoice;
    }

    public function setThemeChoice(?SurveyChoice $ThemeChoice): static
    {
        $this->ThemeChoice = $ThemeChoice;

        return $this;
    }
}
