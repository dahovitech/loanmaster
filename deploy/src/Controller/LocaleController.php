<?php

namespace App\Controller;

use App\Service\Util;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class LocaleController extends AbstractController
{
    public function __construct(
        private Util $util,
        private TokenStorageInterface $tokenStorage
    ) {}

    #[Route(path: '/switch-language/{language}', name: 'switch_language')]
    public function switchLanguage(string $language, Request $request, SessionInterface $session): Response
    {
        if (!in_array($language, $this->util->getLocales(), true)) {
            throw $this->createNotFoundException('Language not supported.');
        }

        $referer = $request->headers->get('referer');
        $user = $this->getUser();

        // Force the default language if the referer URL starts with /admin
        if ($referer && str_starts_with($referer, $request->getSchemeAndHttpHost() . '/admin')) {
            if ($user && (method_exists($user, 'hasRole') && ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_SUPER_ADMIN')))) {
                $language = $this->util->getDefaultLanguage();
            }
        }

        $session->set('language', $language);
        $response = new Response();
        $response->headers->setCookie(new Cookie('language', $language, time() + (3600 * 24 * 30), '/'));

        if ($user && method_exists($user, 'setLocale')) {
            $user->setLocale($language);
        }

        if ($referer) {
            return $this->redirect($referer);
        } 
        
        return $this->redirectToRoute('homepage');
        
    }
}
