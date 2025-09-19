<?php

namespace App\Controller;

use App\Entity\Country;
use App\Service\Util;
use App\Entity\Service;
use App\Entity\ServiceCategory;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\NotificationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class WidgetController extends AbstractController
{
    private $theme;

    public function __construct(
        private TranslatorInterface $translator,
        private Util $util,
        private UrlGeneratorInterface $urlGenerator,
        private NotificationRepository $notificationRepository,
        private KernelInterface $kernel,
        private ParameterBagInterface $parameterBag,
        private EntityManagerInterface $entityManager

    ) {
        $this->theme = $this->util->getSetting()->getTheme();
    }
    
    public function front_header(): Response
    {
    
        return $this->render('@theme/' . $this->theme . '/widget/header.html.twig',[
        ]);
    }

    public function front_footer(): Response
    {
    
        return $this->render('@theme/' . $this->theme . '/widget/footer.html.twig');
    }

    public function back_header(NotificationRepository $notificationRepository): Response
    {
    
        return $this->render('@admin/widget/header.html.twig',[
            'notifications'=>$notificationRepository->findByNotSee($this->getUser()),
        ]);
    }

    public function back_footer(): Response
    {
    
        return $this->render('@admin/widget/footer.html.twig');
    }

    public function back_sidebar(): Response
    {
    
        return $this->render('@admin/widget/sidebar.html.twig');
    }

   
}
