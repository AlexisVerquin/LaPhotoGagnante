<?php

namespace App\Entity;

use App\Repository\ThemeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    // ManyToOne : un même membre peut gagner plusieurs concours.
    // Pas de cascade remove : supprimer un thème ne doit jamais supprimer l'utilisateur gagnant.
    #[ORM\ManyToOne]
    private ?User $winner = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?SurveyChoice $ThemeChoice = null;

    /**
     * @var Collection<int, Photo>
     */
    #[ORM\OneToMany(targetEntity: Photo::class, mappedBy: 'theme', orphanRemoval: true)]
    private Collection $photos;

    #[ORM\Column(length: 255)]
    private ?string $directoryName = null;

    /**
     * @var Collection<int, Vote>
     */
    #[ORM\OneToMany(targetEntity: Vote::class, mappedBy: 'theme')]
    private Collection $votes;

    #[ORM\Column]
    private ?bool $isAlreadyActived = null;

    public function __construct()
    {
        $this->photos = new ArrayCollection();
        $this->votes = new ArrayCollection();
    }

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

    /**
     * @return Collection<int, Photo>
     */
    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function addPhoto(Photo $photo): static
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->setTheme($this);
        }

        return $this;
    }

    public function removePhoto(Photo $photo): static
    {
        if ($this->photos->removeElement($photo)) {
            // set the owning side to null (unless already changed)
            if ($photo->getTheme() === $this) {
                $photo->setTheme(null);
            }
        }

        return $this;
    }

    public function getDirectoryName(): ?string
    {
        return $this->directoryName;
    }

    public function setDirectoryName(string $directoryName): static
    {
        $this->directoryName = $directoryName;

        return $this;
    }

    /**
     * @return Collection<int, Vote>
     */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(Vote $vote): static
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setTheme($this);
        }

        return $this;
    }

    public function removeVote(Vote $vote): static
    {
        if ($this->votes->removeElement($vote)) {
            // set the owning side to null (unless already changed)
            if ($vote->getTheme() === $this) {
                $vote->setTheme(null);
            }
        }

        return $this;
    }

    public function isAlreadyActived(): ?bool
    {
        return $this->isAlreadyActived;
    }

    public function setIsAlreadyActived(bool $isAlreadyActived): static
    {
        $this->isAlreadyActived = $isAlreadyActived;

        return $this;
    }
}
