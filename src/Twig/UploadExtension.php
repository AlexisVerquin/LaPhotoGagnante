<?php

namespace App\Twig;

use App\Entity\Photo;
use App\Entity\Theme;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Extension\AbstractExtension;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\TwigFunction;

class UploadExtension extends AbstractExtension
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

    public function getFunctions(): array
    {
        return [
            new TwigFunction('upload_path', [$this, 'getUploadPath']),
            new TwigFunction('getAvatar', [$this, 'getAvatar']),
            new TwigFunction('getPhotoCurrentTheme', [$this, 'getPhotoCurrentTheme']),
            new TwigFunction('getPathCurrentTheme', [$this, 'getPathCurrentTheme']),
            new TwigFunction('getPathAvatar', [$this, 'getPathAvatar']),
        ];
    }

    public function getAvatar() {
        $user = $this->security->getUser();

        return self::UPLOADS_DIR['avatars'] ."/".$user->getAvatar();

    }

    public function getPathAvatar() {
        return self::UPLOADS_DIR['avatars'];
    }

    public function getPhotoCurrentTheme() {
        $user = $this->security->getUser();

        $actualTheme = $this->em->getRepository(Theme::class)->findActualTheme();
        $hasPhoto = $this->em->getRepository(Photo::class)->findOneBy(['user' => $user, 'theme' => $actualTheme]);

        if (!$hasPhoto) {
            return null;
        }

        return self::UPLOADS_DIR['themes'] ."/".$actualTheme->getDirectoryName()."/".$hasPhoto->getName();
    }

    public function getPathCurrentTheme() {
        $actualTheme = $this->em->getRepository(Theme::class)->findActualTheme();

        return self::UPLOADS_DIR['themes'] ."/".$actualTheme->getDirectoryName()."/";
    }
}
