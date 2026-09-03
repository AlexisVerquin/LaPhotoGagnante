<?php

namespace App\Entity;

use App\Entity\Trait\DateInterface;
use App\Entity\Trait\DateTrait;
use App\Repository\SurveyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SurveyRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Survey implements DateInterface
{
    use DateTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $name = null;

    #[ORM\Column]
    private ?\DateTime $dateStart = null;

    #[ORM\Column]
    private ?\DateTime $dateEnd = null;

    /**
     * @var Collection<int, SurveyChoice>
     */
    #[ORM\OneToMany(targetEntity: SurveyChoice::class, mappedBy: 'survey')]
    private Collection $surveyChoices;

    public function __construct()
    {
        $this->surveyChoices = new ArrayCollection();
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

    /**
     * @return Collection<int, SurveyChoice>
     */
    public function getSurveyChoices(): Collection
    {
        return $this->surveyChoices;
    }

    public function addSurveyChoice(SurveyChoice $surveyChoice): static
    {
        if (!$this->surveyChoices->contains($surveyChoice)) {
            $this->surveyChoices->add($surveyChoice);
            $surveyChoice->setSurvey($this);
        }

        return $this;
    }

    public function removeSurveyChoice(SurveyChoice $surveyChoice): static
    {
        if ($this->surveyChoices->removeElement($surveyChoice)) {
            // set the owning side to null (unless already changed)
            if ($surveyChoice->getSurvey() === $this) {
                $surveyChoice->setSurvey(null);
            }
        }

        return $this;
    }
}
