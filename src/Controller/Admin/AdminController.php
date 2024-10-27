<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\Util;
use App\Entity\Setting;
use App\Form\SettingType;
use App\Entity\Notification;
use App\Form\ChangePasswordType;
use App\Repository\UserRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\NotificationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route(
    path: '/admin',
    name: "app_admin_"
)]
#[IsGranted('ROLE_ADMIN')]

class AdminController extends AbstractController
{

    public function __construct(
        private TranslatorInterface $translator,
        private Util $util,
        private UrlGeneratorInterface $urlGenerator,
        private NotificationRepository $notificationRepository,
        private KernelInterface $kernel,
        private ParameterBagInterface $parameterBag,
        private EntityManagerInterface $entityManager

    ) {}

    #[Route(path: '/', name: 'dashboard')]
    public function dashboard(): Response
    {

        // dd($this->notificationRepository->findByUser($this->getUser()));
        return $this->render('@admin/dashboard.html.twig', [
            'notifications' => $this->notificationRepository->findByUser($this->getUser())
        ]);
    }


    #[Route('/edit_setting', name: 'setting', methods: ["GET", "POST"])]
    public function setting(Request $request, SettingRepository $settingRepository, EntityManagerInterface $em): Response
    {
        // Récupérer seulement le dernier paramètre de configuration
        $param = $settingRepository->findOneBy([], ['id' => 'desc']);

        // Si le paramètre de configuration n'existe pas, en créer un nouveau
        if ($param === null) {
            $param = new Setting();
        }

        $form = $this->createForm(SettingType::class, $param);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Utilisation de Doctrine ORM pour la mise à jour de l'entité
            $em->persist($param);
            $em->flush();

            $this->addFlash('success', "Paramètre mis à jour avec succès");
        }

        return $this->render('@admin/setting.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route("/client/lists", name: "clientLists")]
    public function adminClientLists(): Response
    {
        $users = $this->entityManager->getRepository(User::class)
        ->createQueryBuilder('l')
        ->orderBy('l.id', 'DESC') // Trie par l'ID en ordre décroissant
        ->getQuery()
        ->getResult();
        
        $data = [];
        foreach ($users as $user) {
            if (!$user->hasRole("ROLE_ADMIN") && !$user->hasRole("ROLE_SUPER_ADMIN")) {
                $data[] = $user;
            }
        }
        return $this->render('@admin/user/clientLists.twig', [
            'users' => $data,
        ]);
    }

    #[Route('/client/show/{id}', name: 'clientShow', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('@admin/user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/active_account/{id}', name: 'active_account', methods: ['GET', 'POST'])]
    public function active_account(User $user): Response
    {
        if ($user->isIsEnabled()) {
            $user->setIsEnabled(false);
        } else {
            $user->setIsEnabled(true);
        }

        $this->entityManager->flush();



        $this->addFlash('success', 'Compte mise à jours');

        return $this->redirectToRoute('app_admin_clientLists');
    }

    #[Route('/delete_user/{id}', name: 'delete_user', methods: ['GET'])]
    public function deleteUser($id): Response
    {
        $user = $this->entityManager->getRepository(User::class)->find($id);
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'Utilisateur supprimé avec succès');

        return $this->redirectToRoute('app_admin_clientLists');
    }

    #[Route(path: '/change_password', name: 'change_password')]
    public function changePassword(Request $request, UserRepository $userRepository, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        $user = $this->getUser();
        $user = $userRepository->find($user->getId());

        $form = $this->createForm(ChangePasswordType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$userPasswordHasher->isPasswordValid($user, $form->get('currentPassword')->getData())) {
                $this->addFlash('error', $this->translator->trans("flash.error_currentPassword"));
            } else {
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $form->get('plainPassword')->getData()
                    )
                );

                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $this->addFlash('success', $this->translator->trans("flash.change_password"));
            }
        }
        return $this->render('@admin/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/test_email', name: 'test_email')]
    public function testEmail(Request $request): Response
    {
        $email = $request->request->get('email');
        if (isset($email) && !empty($email)) {
            $this->util->sender(
                $this->util->getSetting()->getEmailSender(),
                "Email de test",
                '@emails/test_email.html.twig',
                [$email],
                []
            );

            $this->addFlash(
                'success',
                "Email envoyer avec succès"
            );
        }

        return $this->render('@admin/test_email.html.twig');
    }
}
