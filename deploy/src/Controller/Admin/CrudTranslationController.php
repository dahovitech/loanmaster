<?php

namespace App\Controller\Admin;

use App\Entity\Language;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Annotation\TranslatableAnnotationReader;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/admin/translation', name: 'app_translation_crud_')]
#[IsGranted('ROLE_ADMIN')]
class CrudTranslationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private TranslatableAnnotationReader $annotationReader
    ) {}

    #[Route('/{entityName}/{entityId}', name: 'index', methods: ['GET'])]
    public function index(Request $request, string $entityName, int $entityId): Response
    {
        $entityName = ucfirst($entityName);
        $entityClass = $this->getEntityClass($entityName);
        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);
        if (!$entity) {
            throw $this->createNotFoundException('Entity not found');
        }

        $translationClass = $this->getTranslationEntityClass($entityName);
        $repository = $this->entityManager->getRepository($translationClass);
        $translations = $repository->findBy(['object' => $entity]);
        $defaultLanguage = $this->entityManager->getRepository(Language::class)->findOneBy(["isDefault" => true])->getCode();

        return $this->render('@admin/translation/index.html.twig', [
            'translations' => $translations,
            'entityName' => $entityName,
            'entityId' => $entityId,
            "defaultLanguage" => $defaultLanguage,
        ]);
    }

    #[Route('/{entityName}/{entityId}/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $entityName, int $entityId): Response
    {
        $entityName = ucfirst($entityName);
        $entityClass = $this->getEntityClass($entityName);
        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);
        if (!$entity) {
            throw $this->createNotFoundException('Entity not found');
        }

        $translationClass = $this->getTranslationEntityClass($entityName);
        $formClass = $this->getTranslationFormClass($entityName);

        $translation = new $translationClass();
        $translation->setObject($entity);
        $form = $this->createForm($formClass, $translation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($translation);
            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('translation.created_successfully', ['%entity%' => $entityName]));
            return $this->redirectToRoute('app_translation_crud_index', ['entityName' => $entityName, 'entityId' => $entity->getId()]);
        }

        return $this->render('@admin/translation/new.html.twig', [
            'form' => $form->createView(),
            'entityName' => $entityName,
            'entityId' => $entityId,
        ]);
    }

    #[Route('/{entityName}/{entityId}/{id}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(Request $request, string $entityName, int $entityId, int $id): Response
    {
        $entityName = ucfirst($entityName);
        $entityClass = $this->getEntityClass($entityName);
        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);
        if (!$entity) {
            throw $this->createNotFoundException('Entity not found');
        }

        $translationClass = $this->getTranslationEntityClass($entityName);
        $formClass = $this->getTranslationFormClass($entityName);

        $translation = $this->entityManager->getRepository($translationClass)->find($id);
        if (!$translation) {
            throw $this->createNotFoundException('Translation not found');
        }

        $form = $this->createForm($formClass, $translation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('translation.updated_successfully', ['%entity%' => $entityName]));
            return $this->redirectToRoute('app_translation_crud_index', ['entityName' => $entityName, 'entityId' => $entity->getId()]);
        }

        return $this->render('@admin/translation/update.html.twig', [
            'form' => $form->createView(),
            'entityName' => $entityName,
            'entityId' => $entityId,
        ]);
    }

    #[Route('/{entityName}/{entityId}/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, string $entityName, int $entityId, int $id): Response
    {
        $entityName = ucfirst($entityName);
        $entityClass = $this->getEntityClass($entityName);
        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);
        if (!$entity) {
            throw $this->createNotFoundException('Entity not found');
        }

        $translationClass = $this->getTranslationEntityClass($entityName);
        $translation = $this->entityManager->getRepository($translationClass)->find($id);
        if (!$translation) {
            throw $this->createNotFoundException('Translation not found');
        }

        if (!$this->isCsrfTokenValid('delete' . $translation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->entityManager->remove($translation);
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('translation.deleted_successfully', ['%entity%' => $entityName]));
        return $this->redirectToRoute('app_translation_crud_index', ['entityName' => $entityName, 'entityId' => $entity->getId()]);
    }


    private function getEntityClass(string $entityName): string
    {
        $entityClass = "App\\Entity\\" . $entityName;
        if (!class_exists($entityClass)) {
            throw $this->createNotFoundException('Entity not found');
        }
        return $entityClass;
    }

    private function getTranslationEntityClass(string $entityName): string
    {
        $entityClass = "App\\Entity\\" . $entityName . "Translation";
        if (!class_exists($entityClass)) {
            throw $this->createNotFoundException('Translation entity not found');
        }
        return $entityClass;
    }

    private function getTranslationFormClass(string $entityName): string
    {
        $formClass = "App\\Form\\" . $entityName . "TranslationType";
        if (!class_exists($formClass)) {
            throw $this->createNotFoundException('Translation form not found');
        }
        return $formClass;
    }

    #[Route('/{entityName}/{entityId}/delete_selected', name: 'delete_selected', methods: ['POST'])]
    public function deleteSelected(Request $request, string $entityName, int $entityId): Response
    {
        $entityName = ucfirst($entityName);
        $entityClass = $this->getEntityClass($entityName);
        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);
        if (!$entity) {
            throw $this->createNotFoundException('Entity not found');
        }
    
        $translationClass = $this->getTranslationEntityClass($entityName);
        $selectedIds = $request->request->all('selected'); // Utilisation de all() pour obtenir un tableau
    
        $token= $request->request->get('token');
        if (!$this->isCsrfTokenValid('delete_selected', $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    
        foreach ($selectedIds as $id) {
            $translation = $this->entityManager->getRepository($translationClass)->find($id);
            if ($translation) {
                $this->entityManager->remove($translation);
            }
        }
    
        $this->entityManager->flush();
    
        $this->addFlash('success', $this->translator->trans('translation.deleted_successfully', ['%entity%' => $entityName]));
        return $this->redirectToRoute('app_translation_crud_index', ['entityName' => $entityName, 'entityId' => $entity->getId()]);
    }
    

    #[Route('/{entityName}/{entityId}/delete_all', name: 'delete_all', methods: ['GET'])]
    public function deleteAll(Request $request, string $entityName, int $entityId): Response
    {
        $entityName = ucfirst($entityName);
        $entityClass = $this->getEntityClass($entityName);
        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);
        if (!$entity) {
            throw $this->createNotFoundException('Entity not found');
        }

        $translationClass = $this->getTranslationEntityClass($entityName);
        $translations = $this->entityManager->getRepository($translationClass)->findBy(['object' => $entity]);

        foreach ($translations as $translation) {
            $this->entityManager->remove($translation);
        }

        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('translation.deleted_successfully', ['%entity%' => $entityName]));
        return $this->redirectToRoute('app_translation_crud_index', ['entityName' => $entityName, 'entityId' => $entity->getId()]);
    }
}
