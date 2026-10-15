<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Theme;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Attribute\AsTwigFunction;

class ThemeExtension
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[AsTwigFunction('getCurrentTheme')]
    public function getCurrentTheme(): ?Theme
    {
        return $this->em->getRepository(Theme::class)->findActualTheme();
    }

    #[AsTwigFunction('getCurrentThemeTime')]
    public function getCurrentThemeTime(): array
    {
        $actualTheme = $this->em->getRepository(Theme::class)->findActualTheme();
        $dateForEnableVote = clone $actualTheme->getDateEnd();
        $dateForEnableVote->modify('-6 day');

        return [
            'themeEnd' => $actualTheme->getDateEnd(),
            'enableVote' => $dateForEnableVote,
        ];
    }
}
