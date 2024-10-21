<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ResetPasswordRequestType;
use App\Form\ResetPasswordType;
use App\Service\Util;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(
    path: '/{_locale}',
    
)]
class ResetPasswordController extends AbstractController
{
    private Util $util;
    private TranslatorInterface $translator;
    private $theme;

    public function __construct(Util $util, TranslatorInterface $translator)
    {
        $this->util = $util;
        $this->translator = $translator;
        $this->theme = $this->util->getSetting()->getTheme();
    }

    #[Route('/reset-password', name: 'reset_password_request')]
    public function request(Request $request, EntityManagerInterface $entityManager, TokenGeneratorInterface $tokenGenerator): Response
    {
        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $form->get('email')->getData()]);

            if (!$user) {
                $this->addFlash('error', $this->translator->trans('resetpassword.email_not_found'));
                return $this->redirectToRoute('reset_password_request');
            }

            $token = $tokenGenerator->generateToken();
            $user->setResetToken($token);
            $entityManager->flush();

            $this->sendResetPasswordEmail($user);

            $this->addFlash('success', $this->translator->trans('resetpassword.emailsent'));
            return $this->redirectToRoute('login');
        }

        return $this->render('@theme/' . $this->theme . '/reset_password/request.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reset-password/{token}', name: 'reset_password')]
    public function reset(Request $request, string $token, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['resetToken' => $token]);

        if (!$user) {
            $this->addFlash('error', $this->translator->trans('resetpassword.token_invalid'));
            return $this->redirectToRoute('reset_password_request');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );
            $user->setResetToken(null);
            $entityManager->flush();

            $this->addFlash('success', $this->translator->trans('resetpassword.success'));
            return $this->redirectToRoute('login');
        }

        return $this->render('@theme/' . $this->theme . '/reset_password/reset.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function sendResetPasswordEmail(User $user): void
    {
        $setting = $this->util->getSetting();
        $this->util->sender(
            $setting->getEmailSender(),
            $setting->getTitle() . ' - ' . $this->translator->trans('resetpassword.email.subject'),
            '@emails/reset_password.html.twig',
            [$user->getEmail()],
            [
                'resetToken' => $user->getResetToken(),
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
