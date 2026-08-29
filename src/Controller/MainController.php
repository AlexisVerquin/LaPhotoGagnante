<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Constant\AlertConst;
use Symfony\Component\Translation\LocaleFallbackProvider;

final class MainController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    #[Route('/', name: 'app_main')]
    public function index(Request $request): Response
    {
        $locale = $request->getPreferredLanguage(['pt', 'fr_Latn_CH', 'en_US'] );
        LocaleFallbackProvider::validateLocale($locale);

        if ($this->getUser() && !$this->getUser()->isVerified()) {
            $message = $this->translator->trans(AlertConst::ALERT_MSG_USER_UNVERIFIED);
            $this->addFlash('USER_NOT_VERIFIED', $message);
        }

        return $this->render('main/index.html.twig', [
        ]);
    }

    #[Route('/locale/{locale}', name: 'app_change_locale')]
    public function changeLocale(string $locale, Request $request): Response
    {
        $request->getSession()->set('_locale', $locale);
        return $this->redirectToRoute('app_main');
    }
}
