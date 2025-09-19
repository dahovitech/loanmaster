<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class TestController extends AbstractController
{
    #[Route('/test', name: 'test')]
    public function test(): Response
    {
        return new Response('<h1>Test Controller fonctionne !</h1><p>'.date('Y-m-d H:i:s').'</p>');
    }

    #[Route('/homepage', name: 'homepage_test')]
    public function homepageTest(): Response
    {
        return $this->render('theme/loan/home.html.twig', []);
    }

    #[Route('/', name: 'home_direct')]
    public function homeDirect(): Response
    {
        return $this->render('theme/loan/home.html.twig', []);
    }

    #[Route('/about-test', name: 'about_test')]
    public function aboutTest(): Response
    {
        return $this->render('theme/loan/about.html.twig', []);
    }

    #[Route('/contact-test', name: 'contact_test')]
    public function contactTest(): Response
    {
        return $this->render('theme/loan/contact.html.twig', []);
    }

    #[Route('/services-test', name: 'services_test')]
    public function servicesTest(): Response
    {
        return $this->render('theme/loan/service/index.html.twig', []);
    }
}
