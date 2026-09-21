<?php

namespace App\Twig;

use App\Entity\Photo;
use App\Entity\Theme;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Attribute\AsTwigFunction;
use Symfony\Bundle\SecurityBundle\Security;

class UploadExtension
{
    private const UPLOADS_DIR = [
        "avatars" => "uploads/avatars",
        "images" => "uploads/images",
        "photos" => "uploads/photos",
        "themes" => "uploads/themes",
    ];
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security
    ) {}

    #[AsTwigFunction('getAvatar')]
    public function getAvatar() {
        $user = $this->security->getUser();

        return self::UPLOADS_DIR['avatars'] ."/".$user->getAvatar();

    }

    #[AsTwigFunction('getPathAvatar')]
    public function getPathAvatar() {
        return self::UPLOADS_DIR['avatars'];
    }

    #[AsTwigFunction('getPhotoCurrentTheme')]
    public function getPhotoCurrentTheme() {
        $user = $this->security->getUser();

        $actualTheme = $this->em->getRepository(Theme::class)->findActualTheme();
        $hasPhoto = $this->em->getRepository(Photo::class)->findOneBy(['user' => $user, 'theme' => $actualTheme]);

        if (!$hasPhoto) {
            return null;
        }

        return [
            'image' =>self::UPLOADS_DIR['themes'] ."/".$actualTheme->getDirectoryName()."/".$hasPhoto->getName(),
            'vote' => count($hasPhoto->getVotes())
        ];
    }

    #[AsTwigFunction('getPathCurrentTheme')]
    public function getPathCurrentTheme() {
        $actualTheme = $this->em->getRepository(Theme::class)->findActualTheme();

        return self::UPLOADS_DIR['themes'] ."/".$actualTheme->getDirectoryName()."/";
    }
}
