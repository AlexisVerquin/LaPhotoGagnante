<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entity\Theme;
use App\Repository\ThemeRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Oublie le « concours en cours » mémorisé dès qu'un concours est créé, modifié ou supprimé.
 */
#[AsEntityListener(event: Events::postPersist, entity: Theme::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Theme::class)]
#[AsEntityListener(event: Events::postRemove, entity: Theme::class)]
final class ThemeCacheInvalidator
{
    public function __construct(private ThemeRepository $themeRepository)
    {
    }

    public function __invoke(): void
    {
        $this->themeRepository->reset();
    }
}
