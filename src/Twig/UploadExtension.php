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
            // L'avatar par défaut vit dans public/images, car public/uploads n'est ni dans git ni dans l'image Docker.
            return 'images/default-avatar.jpg';
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
            // Validée, la photo est publique ; en attente, elle passe par le contrôleur qui vérifie les droits.
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
