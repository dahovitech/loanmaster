<?php

namespace App\Controller;

use Dompdf\Dompdf;
use App\Entity\Page;
use App\Service\Util;
use App\Entity\Contact;
use App\Entity\Country;
use App\Entity\Service;
use App\Service\ApiService;
use App\Form\ContactFormType;
use App\Entity\PageTranslation;
use App\Entity\ServiceTranslation;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\NotificationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route(
    path: '/{_locale}',

)]
class FrontController extends AbstractController
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

    #[Route(path: '/', name: 'home')]
    public function homepage(ApiService $apiService): Response
    {
        // $htmlContent="<html></html>";
        // $dompdf = new Dompdf();
        // $dompdf->loadHtml($htmlContent);
        // $dompdf->setPaper('A4', 'portrait');
        // $dompdf->render();
        
        // // Output the generated PDF to Browser
        // $dompdf->stream("contrat.pdf", ["Attachment" => true]);
        // return $dompdf;
        return $this->render('@theme/' . $this->theme . '/home.html.twig', []);
    }

    #[Route(path: '/about', name: 'about')]
    public function about(): Response
    {

        return $this->render('@theme/' . $this->theme . '/about.html.twig');
    }

    #[Route('/contact', name: 'contact')]
    public function contact(Request $request, EntityManagerInterface $entityManager): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactFormType::class, $contact);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($contact);
            $entityManager->flush();

            $context= [
                'contact' => $contact,
            ];
    
            $this->util->sender(
                $this->util->getSetting()->getEmailSender(),
                "Nouveau message de contact",
                '@emails/contact.html.twig',
                [$this->util->getSetting()->getEmail()],
                $context
            );
            
            $this->addFlash('success', $this->translator->trans("contact.successMessage"));
        }

        return $this->render('@theme/' . $this->theme . '/contact.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/newletter', name: 'newletter')]
    public function newletter(Request $request): Response
    {

        $context= [
            'useremail' => $request->request->get("email"),
        ];

        $this->util->sender(
            $this->util->getSetting()->getEmailSender(),
            "Abonnement newsletter",
            '@emails/newsletter.html.twig',
            [$this->util->getSetting()->getEmail()],
            $context
        );
        
        $this->addFlash('success', $this->translator->trans("newsletterSuccess"));

        
        $referer = $request->headers->get('referer');

        if ($referer) {
            return $this->redirect($referer);
        } 

        return $this->redirectToRoute('home');
        
    }



    #[Route(path: '/page/{slug}', name: 'page_show')]
    public function page_show($slug): Response
    {
        $page = $this->entityManager->getRepository(Page::class)->findOneBy(["slug" => $slug, "isEnabled" => true]);
        if(!$page){
            $translation = $this->entityManager->getRepository(PageTranslation::class)->findOneBy(["field" => "slug", "content" => trim(strip_tags($slug))]);
            $page= $translation->getObject();
        }
        return $this->render('@theme/' . $this->theme . '/page/show.html.twig', [
            "page" => $page
        ]);
    }

    #[Route(path: '/services', name: 'service_index')]
    public function service_index(): Response
    {
        return $this->render('@theme/' . $this->theme . '/service/index.html.twig', []);
    }

    #[Route(path: '/service/{slug}', name: 'service_show')]
    public function service_show($slug): Response
    {
        $service = $this->entityManager->getRepository(Service::class)->findOneBy(["slug" => $slug, "isEnabled" => true]);

        if(!$service){

            $translation = $this->entityManager->getRepository(ServiceTranslation::class)->findOneBy(["field" => "slug", "content" => trim(strip_tags($slug))]);

            $service= $translation->getObject();
        }
        return $this->render('@theme/' . $this->theme . '/service/show.html.twig', [
            "service" => $service
        ]);
    }

}
