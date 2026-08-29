<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class UploadExtension extends AbstractExtension
{
    public function __construct(
        private string $uploadsPublicPath = '/uploads'
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('upload_path', [$this, 'getUploadPath']),
        ];
    }

    public function getUploadPath(?string $filename, string $type = "avatar"): ?string
    {
        if (!$filename) {
            return null;
        }

        if ($type === "avatar") {
            $this->uploadsPublicPath = "{$this->uploadsPublicPath}/avatars";
        }

        return sprintf('%s/%s', $this->uploadsPublicPath, $filename);
    }
}
