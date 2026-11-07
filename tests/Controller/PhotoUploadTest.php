<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Photo;
use App\Tests\FunctionalTestCase;
use App\Tests\TestImage;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[RequiresPhpExtension('gd')]
#[RequiresPhpExtension('exif')]
final class PhotoUploadTest extends FunctionalTestCase
{
    private function csrfToken(): string
    {
        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        return (string) $crawler->filter('input[data-url="/upload/photo_challenge"]')->attr('data-token');
    }

    /**
     * @return array<string, mixed>
     */
    private function upload(?string $token = null): array
    {
        $path = TestImage::jpegWithExif(sys_get_temp_dir() . '/' . uniqid('upload-') . '.jpg');
        $file = new UploadedFile($path, 'Ma Photo.jpg', 'image/jpeg', null, true);

        $this->client->request('POST', '/upload/photo_challenge', [], ['file' => $file], [
            'HTTP_X_CSRF_TOKEN' => $token ?? $this->csrfToken(),
        ]);

        return $this->jsonResponse();
    }

    public function testPhotoIsSavedPendingValidationAndWithoutExif(): void
    {
        $theme = $this->createTheme();
        $this->client->loginUser($this->createUser());

        $data = $this->upload();

        self::assertResponseIsSuccessful();
        self::assertTrue($data['success']);

        $photo = $this->em->getRepository(Photo::class)->findOneBy([]);
        self::assertInstanceOf(Photo::class, $photo);
        self::assertFalse($photo->isVisible(), 'La photo doit attendre la validation admin');

        $path = static::getContainer()->getParameter('app.pending_photos_dir')
            . '/' . $theme->getDirectoryName() . '/' . $photo->getName();
        self::assertFileExists($path, 'La photo en attente doit être rangée hors du dossier web');
        self::assertFileDoesNotExist(static::getContainer()->getParameter('app.public_photos_dir')
            . '/' . $theme->getDirectoryName() . '/' . $photo->getName());

        $exif = @exif_read_data($path) ?: [];
        self::assertArrayNotHasKey('Make', $exif);
        self::assertArrayNotHasKey('GPSLatitudeRef', $exif);
    }

    public function testOnlyOnePhotoPerContest(): void
    {
        $this->createTheme();
        $this->client->loginUser($this->createUser());
        $token = $this->csrfToken();

        $this->upload($token);
        $data = $this->upload($token);

        self::assertResponseStatusCodeSame(409);
        self::assertArrayHasKey('error', $data);
        self::assertSame(1, $this->em->getRepository(Photo::class)->count());
    }

    public function testAnonymousCannotUpload(): void
    {
        $this->createTheme();

        $this->upload();

        self::assertResponseStatusCodeSame(401);
        self::assertSame(0, $this->em->getRepository(Photo::class)->count());
    }

    public function testUnverifiedMemberCannotUpload(): void
    {
        $this->createTheme();
        $this->client->loginUser($this->createUser(verified: false));

        $this->upload();

        self::assertResponseStatusCodeSame(403);
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $this->createTheme();
        $this->client->loginUser($this->createUser());

        $this->upload('mauvais-jeton');

        self::assertResponseStatusCodeSame(419);
    }
}
