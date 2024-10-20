<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;


class DefaultController extends AbstractController
{
    #[Route(path: '/', name: 'app_default')]
    public function defaultpage(Request $request): Response
    {
       
         return $this->redirectToRoute('home',[
            '_locale' => $request->getLocale(),
        ],);
    }

    

    
}
