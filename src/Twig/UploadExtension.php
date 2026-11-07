<?php

namespace App\Twig;

use App\Entity\Photo;
use App\Entity\Theme;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

class UploadExtension
{
    private const UPLOADS_DIR = [
        'avatars' => 'uploads/avatars',
        'images' => 'uploads/images',
        'photos' => 'uploads/photos',
        'themes' => 'uploads/themes',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
        private Packages $packages,
    ) {
    }

    #[AsTwigFunction('getAvatar')]
    public function getAvatar(?User $user = null): string
    {
        if (!$user) {
            $currentUser = $this->security->getUser();
            $user = $currentUser instanceof User ? $currentUser : null;
        }
        $avatar = $user?->getAvatar();
        if (!$avatar) {
            $avatar = 'default.jpg';
        }

        return self::UPLOADS_DIR['avatars'] . '/' . $avatar;
    }

    #[AsTwigFunction('getPathAvatar')]
    public function getPathAvatar(): string
    {
        return self::UPLOADS_DIR['avatars'];
    }

    /**
     * @return array{imageUrl: string, isVisible: bool|null, vote: int}|null
     */
    #[AsTwigFunction('getPhotoCurrentTheme')]
    public function getPhotoCurrentTheme(): ?array
    {
        $user = $this->security->getUser();

        $actualTheme = $this->em->getRepository(Theme::class)->findActualTheme();
        if (!$user || !$actualTheme) {
            return null;
        }
        $hasPhoto = $this->em->getRepository(Photo::class)->findOneBy(['user' => $user, 'theme' => $actualTheme]);

        if (!$hasPhoto) {
            return null;
        }

        return [
            // Photo validée : fichier public ; en attente : servie par le contrôleur (auteur/admin uniquement)
            'imageUrl' => $hasPhoto->isVisible()
                ? $this->packages->getUrl(self::UPLOADS_DIR['themes'] . '/' . $actualTheme->getDirectoryName() . '/' . $hasPhoto->getName())
                : $this->urlGenerator->generate('app_photo_image', ['id' => $hasPhoto->getId()]),
            'isVisible' => $hasPhoto->isVisible(),
            'vote' => count($hasPhoto->getVotes()),
        ];
    }

    #[AsTwigFunction('getPathCurrentTheme')]
    public function getPathCurrentTheme(): string
    {
        $actualTheme = $this->em->getRepository(Theme::class)->findActualTheme();
        if (!$actualTheme) {
            return '';
        }

        return self::UPLOADS_DIR['themes'] . '/' . $actualTheme->getDirectoryName() . '/';
    }
}
