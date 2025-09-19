<?php

namespace App\Controller;

use App\Entity\Language;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;


class DefaultController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator
    ) {}

    #[Route(path: '/', name: 'app_default')]
    public function defaultpage(Request $request): Response
    {
        $defaultLanguage = $this->entityManager->getRepository(Language::class)->findOneBy(["isDefault" => true])->getCode();

         return $this->redirectToRoute('home',[
            '_locale' => $defaultLanguage,
        ],);
    }

    

    
}
