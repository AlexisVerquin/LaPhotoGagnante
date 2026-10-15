<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Photo;
use App\Entity\Theme;
use Doctrine\ORM\EntityManagerInterface;

class PhotoService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function getPhotoWinnerLastTheme(): ?Photo
    {
        $lastTheme = $this->entityManager->getRepository(Theme::class)->findLastFinishedTheme();
        if (!$lastTheme) {
            return null;
        }

        foreach ($lastTheme->getWinner()->getPhotos() as $photo) {
            if ($photo->getTheme()->getId() === $lastTheme->getId()) {
                return $photo;
            }
        }

        return null;
    }

    public function getPhotoOfTheDay(): ?array
    {
        $votes = $this->entityManager->getRepository(Photo::class)->findPhotosOfTheDay();

        return $votes[0] ?? null;
    }
}
