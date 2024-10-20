<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Service\Util;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    path: '/{_locale}',

)]
class RegistrationController extends AbstractController
{

    private $theme;

    public function __construct(
        private Util $util,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator
    ) {

        $this->theme = $this->util->getSetting()->getTheme();
    }

    #[Route('/register', name: 'register')]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, TokenGeneratorInterface $tokenGenerator, Util $util, TranslatorInterface $translator): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Encode the plain password
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Generate a confirmation token
            $token = $tokenGenerator->generateToken();
            $user->setConfirmationToken($token);

            $entityManager->persist($user);
            $entityManager->flush();

            // Send confirmation email
            $this->sendConfirmationEmail($user, $util);

            // Add flash message
            $this->addFlash('success', $translator->trans('registration.success'));

            // Redirect or do something else
            return $this->redirectToRoute('login');
        }

        return $this->render('@theme/' . $this->theme . '/registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/register/confirm/{token}', name: 'register_confirm_token')]
    public function confirmToken(string $token, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['confirmationToken' => $token]);

        if (!$user) {
            $this->addFlash('error', $translator->trans('registration.token_invalid'));
            return $this->redirectToRoute('register');
        }

        $user->setIsVerified(true);
        $user->setConfirmationToken(null);
        $entityManager->flush();

        $this->addFlash('success', $translator->trans('registration.confirmed'));

        return $this->redirectToRoute('login');
    }

    private function sendConfirmationEmail(User $user, Util $util): void
    {
        $setting = $util->getSetting();
        $url = $this->urlGenerator->generate("register_confirm_token", ["token" => $user->getConfirmationToken()], 0);
        $util->sender(
            $setting->getEmailSender(),
            $setting->getTitle() . ' - ' . $this->translator->trans('registration.subject'),
            '@emails/confirmation_email.html.twig',
            [$user->getEmail()],
            [
                'url' => $url,
                'title' => $setting->getTitle(),
                'logoDark' => $setting->getLogoDark(),
                'logoLight' => $setting->getLogoLight(),
                'emailImg' => $setting->getEmailImg(),
                'favicon' => $setting->getFavicon(),
                'address' => $setting->getAddress(),
                'emailSender' => $setting->getEmailSender(),
                'telephone' => $setting->getTelephone(),
                'devise' => $setting->getDevise(),
            ]
        );
    }
}
