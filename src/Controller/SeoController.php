<?php

namespace App\Controller;

use App\Entity\Theme;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * robots.txt et sitemap.xml, générés pour que seul le domaine de production soit indexé (la preprod reste invisible).
 */
final class SeoController extends AbstractController
{
    public function __construct(
        #[Autowire('%app.site_url%')]
        private string $siteUrl,
    ) {
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(Request $request): Response
    {
        $response = $this->render('seo/robots.txt.twig', ['indexable' => $this->isProductionHost($request)]);
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        return $response->setPublic()->setMaxAge(3600);
    }

    /**
     * @param array{updated_at: string} $legal
     */
    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(EntityManagerInterface $em, #[Autowire('%app.legal%')] array $legal): Response
    {
        $legalUpdate = new \DateTimeImmutable($legal['updated_at']);
        $pages = [
            ['path' => $this->generateUrl('app_main'), 'lastmod' => null],
            ['path' => $this->generateUrl('app_register'), 'lastmod' => null],
            ['path' => $this->generateUrl('app_contact'), 'lastmod' => null],
            ['path' => $this->generateUrl('app_cgu'), 'lastmod' => $legalUpdate],
            ['path' => $this->generateUrl('app_rgpd'), 'lastmod' => $legalUpdate],
            ['path' => $this->generateUrl('app_legal_notice'), 'lastmod' => $legalUpdate],
        ];
        // La galerie n'existe que pendant un concours (sinon elle redirige vers l'accueil)
        if ($theme = $em->getRepository(Theme::class)->findActualTheme()) {
            array_splice($pages, 1, 0, [['path' => $this->generateUrl('app_gallery'), 'lastmod' => $theme->getDateStart()]]);
        }

        $response = $this->render('seo/sitemap.xml.twig', ['pages' => $pages, 'base' => rtrim($this->siteUrl, '/')]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response->setPublic()->setMaxAge(3600);
    }

    private function isProductionHost(Request $request): bool
    {
        return $request->getHost() === parse_url($this->siteUrl, \PHP_URL_HOST);
    }
}
