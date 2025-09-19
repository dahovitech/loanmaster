<?php

namespace App\EventSubscriber;

use App\Service\Util;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class LanguageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Util $util,
        private TokenStorageInterface $tokenStorage,
        private EntityManagerInterface $entityManager
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => 'onKernelRequest',
            SecurityEvents::INTERACTIVE_LOGIN => 'onInteractiveLogin',
        ];
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if ($user && $user->getLocale()) {
            $event->getRequest()->getSession()->set('_locale', $user->getLocale());
        }
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $defaultLocale = $this->util->getDefaultLanguage();
        $locale = $request->attributes->get('_locale', $defaultLocale);
       
        if (!in_array($locale, $this->util->getLocales())) {
            $request->getSession()->set('_locale', $defaultLocale);
        }

        // try to see if the locale has been set as a _locale routing parameter
        if ($locale) {
            $request->getSession()->set('_locale', $locale);
        } else {
            // if no explicit locale has been set on this request, use one from the session
            $request->setLocale($request->getSession()->get('_locale', $defaultLocale));
        }
        if ($this->tokenStorage->getToken()?->getUser()) {
            $user = $this->tokenStorage->getToken()->getUser();
            if ($user && $user->getLocale() !== $locale) {
                $user->setLocale($locale);
                $this->entityManager->flush();
            }
        }

        $referer = $request->headers->get('referer');
        if ($referer && strpos($referer, $request->getSchemeAndHttpHost() . '/admin') === 0) {
            if ($this->tokenStorage->getToken()?->getUser() &&
                ($this->tokenStorage->getToken()->getUser()->hasRole('ROLE_ADMIN') ||
                    $this->tokenStorage->getToken()->getUser()->hasRole('ROLE_SUPER_ADMIN'))
            ) {
                $request->getSession()->set('_locale', $defaultLocale);
            }
        }
    }
}
