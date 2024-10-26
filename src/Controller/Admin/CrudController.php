<?php

namespace App\Controller\Admin;

use App\Entity\Language;
use App\Service\ApiService;
use InvalidArgumentException;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Annotation\SlugableAttributeReader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Annotation\ConfigurableAttributeReader;
use Symfony\Component\Routing\Annotation\Route;
use App\Annotation\TranslatableAnnotationReader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/admin/entity', name: 'app_crud_')]
#[IsGranted('ROLE_ADMIN')]
class CrudController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private LanguageRepository $languageRepository,
        private ApiService $apiService,
        private TranslatableAnnotationReader $annotationReader,
        private ConfigurableAttributeReader  $configurableAttributeReader,
        private SlugableAttributeReader $slugableReader,
        private SluggerInterface $slugger

    ) {}

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $entityName = $this->getEntityName($request);
        $entityConfig = $this->getEntityConfig($request);

        //dd($entityConfig->getId());
        if ($entityConfig) {
            return $this->redirectToRoute('app_crud_update', ['entityName' => $entityName, 'id' => $entityConfig->getId()]);
        }
        $entityClass = $this->getEntityClass($entityName);

        $repository = $this->entityManager->getRepository($entityClass);
        $entities = $repository->findAll();

        return $this->render('@admin/crud/' . strtolower($entityName) . '/index.html.twig', [
            'entities' => $entities,
            "entityName" => $entityName,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {

        $entityName = $this->getEntityName($request);
        $entityConfig = $this->getEntityConfig($request);
        if ($entityConfig) {
            return $this->redirectToRoute('app_crud_update', ['entityName' => $entityName, 'id' => $entityConfig->getId()]);
        }
        $entityClass = $this->getEntityClass($entityName);
        $formClass = $this->getFormClass($entityName);

        $entity = new $entityClass();
        $form = $this->createForm($formClass, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($entity);

            if ($this->isTranslatableEntity($entityClass)) {
                $this->handleTranslations($entity, $entityName);
            }

            $this->handleDefaultEntity($entity, $entityClass);

            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('entity.created_successfully', ['%entity%' => $entityName]));
            return $this->redirectToRoute('app_crud_index', ['entityName' => $entityName]);
        }

        return $this->render('@admin/crud/' . strtolower($entityName) . '/new.html.twig', [
            'entity' => $entity,
            'form' => $form->createView(),
            "entityName" => $entityName
        ]);
    }

    #[Route('/{id}/update', name: 'update', methods: ['GET', 'POST'])]
    public function update(Request $request, int $id): Response
    {
        $entityName = $this->getEntityName($request);
        $entityClass = $this->getEntityClass($entityName);
        $formClass = $this->getFormClass($entityName);

        $entity = $this->entityManager->getRepository($entityClass)->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('Entity not found');
        }

        $form = $this->createForm($formClass, $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // if ($this->isTranslatableEntity($entityClass)) {
            //     $this->handleTranslations($entity, $entityName);
            // }

            $this->handleDefaultEntity($entity, $entityClass);


            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('entity.updated_successfully', ['%entity%' => $entityName]));

            return $this->redirectToRoute('app_crud_index', ['entityName' => $entityName]);
        }

        return $this->render('@admin/crud/' . strtolower($entityName) . '/update.html.twig', [
            'entity' => $entity,
            'form' => $form->createView(),
            "entityName" => $entityName
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $entityName = $this->getEntityName($request);
        $entityConfig = $this->getEntityConfig($request);
        if ($entityConfig) {
            return $this->redirectToRoute('app_crud_update', ['entityName' => $entityName, 'id' => $entityConfig->getId()]);
        }
        $entityClass = $this->getEntityClass($entityName);

        $entity = $this->entityManager->getRepository($entityClass)->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('Entity not found');
        }

        if (!$this->isCsrfTokenValid('delete' . $entity->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('entity.deleted_successfully', ['%entity%' => $entityName]));
        return $this->redirectToRoute('app_crud_index', ['entityName' => $entityName]);
    }

    #[Route('/{id}/show', name: 'show', methods: ['GET'])]
    public function show(Request $request, int $id): Response
    {
        $entityName = $this->getEntityName($request);

        $entityConfig = $this->getEntityConfig($request);
        if ($entityConfig) {
            return $this->redirectToRoute('app_crud_update', ['entityName' => $entityName, 'id' => $entityConfig->getId()]);
        }

        $entityClass = $this->getEntityClass($entityName);

        $entity = $this->entityManager->getRepository($entityClass)->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('Entity not found');
        }

        return $this->render('@admin/crud/' . strtolower($entityName) . '/show.html.twig', [
            'entity' => $entity,
            "entityName" => $entityName
        ]);
    }

    #[Route('/{entityId}/createtranslations', name: 'create_translations', methods: ['GET', 'POST'])]
    public function create_translations(Request $request,int $entityId): Response
    {
        $entityName = $this->getEntityName($request);        
        $entityClass = $this->getEntityClass($entityName);
        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);

        if ($this->isTranslatableEntity($entityClass)) {
            $this->handleTranslations($entity, $entityName);
        }

        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('translation.created_successfully', ['%entity%' => $entityName]));
        return $this->redirectToRoute('app_translation_crud_index', ['entityName' => $entityName, 'entityId' => $entity->getId()]);

    }

    #[Route('/{id}/getentity', name: 'get_entity', methods: ['GET'])]
    public function get(Request $request, int $id): Response
    {
        $entityName = $this->getEntityName($request);
        $entityClass = $this->getEntityClass($entityName);

        $entity = $this->entityManager->getRepository($entityClass)->find($id);
        if (!$entity) {
            throw $this->createNotFoundException('Entity not found');
        }

        $languages = $this->languageRepository->findByPublishNotDefault();
        $serializedLanguages = array_map([$this, 'serializeLanguage'], $languages);
        $defaultLanguage = $this->languageRepository->findOneBy(["isDefault" => true])->getCode();


        return new JsonResponse([
            "entity" => $this->entitySerialize($entityClass, $entity),
            "languages" => $serializedLanguages,
            "defaultLanguage" => $defaultLanguage,
            "fields" => $this->annotationReader->getTranslatableField($entityClass)
        ]);
    }

    private function entitySerialize($entityClass, $entity): array
    {

        $fields = $this->annotationReader->getTranslatableField($entityClass);
        $entitySerialize = [];
        foreach ($fields as $field) {
            $method = "get" . ucfirst($field);
            $entitySerialize[$field] = $entity->$method();
        }

        return $entitySerialize;
    }

    #[Route(path: '/translate_text', name: 'translate_text', methods: ['POST'])]
    public function translateText(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $text = $data['text'] ?? null;
        $locale = $data['locale'] ?? null;
        $source = $data['source'] ?? null;

        if (empty($text) || empty($locale) || empty($source)) {
            return new JsonResponse(['error' => 'Missing required parameters'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $translatedText = $this->apiService->translate([
                'q' => $text,
                'source' => $source,
                'target' => $locale,
            ]);
            return new JsonResponse($translatedText);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/translate_all_save', name: 'translate_all_save', methods: ['POST'])]
    public function saveAllTranslation(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $entityName = ucfirst($data['entityName'] ?? '');
        $entityId = intval($data['entityId']) ?? null;
        $translations = $data['translations'] ?? [];

        if (empty($entityName) || empty($entityId) || empty($translations)) {
            return new JsonResponse(['error' => 'Missing required parameters'], Response::HTTP_BAD_REQUEST);
        }

        $entityClass = $this->getEntityClass($entityName);
        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);

        if (!$entity) {
            return new JsonResponse(['error' => 'Entity not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            foreach ($translations as $translation) {
                $this->updateEntityTranslation($entityName, $entityId, $translation);
            }

            $this->entityManager->flush();

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/translate_save', name: 'translate_save', methods: ['POST'])]
    public function saveTranslation(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $entityName = ucfirst($data['entityName'] ?? '');
        $entityId = intval($data['entityId']) ?? null;
        $translations = $data['translations'] ?? [];

        if (empty($entityName) || empty($entityId) || empty($translations)) {
            return new JsonResponse(['error' => 'Missing required parameters'], Response::HTTP_BAD_REQUEST);
        }

        $entityClass = $this->getEntityClass($entityName);
        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);


        if (!$entity) {
            return new JsonResponse(['error' => 'Entity not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            foreach ($translations as $translation) {
                $this->updateEntityTranslation($entityName, $entityId, $translation);
            }

            $this->entityManager->flush();

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function updateEntityTranslation($entityName, $entityId, array $translationData): void
    {

        $translationClass = $this->getTranslationEntityClass($entityName);
        $entityClass = $this->getEntityClass($entityName);

        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);

        $translation = $this->entityManager->getRepository($translationClass)->findOneBy([
            'object' =>  $entity,
            'locale' => $translationData['locale'],
            'field' => $translationData['field'],
        ]);

        if (!$translation) {
            $translation = new $translationClass();
            $translation->setObject($entity);
        }


        $translation->setLocale($translationData['locale']);
        $translation->setField($translationData['field']);
        if ($this->slugableReader->isSlugable($entityClass, $translationData['field'])) {
            $translation->setContent($this->slugger->slug($translationData['content']));
        } else {
            $translation->setContent($translationData['content']);
        }


        $this->entityManager->persist($translation);
    }

    private function getTranslationEntityClass(string $entityName): string
    {
        $entityClass = "App\\Entity\\" . $entityName . "Translation";
        if (!class_exists($entityClass)) {
            throw $this->createNotFoundException('Translation entity not found');
        }
        return $entityClass;
    }

    private function serializeLanguage(Language $language): array
    {
        return [
            'code' => $language->getCode(),
            'name' => $language->getName(),
            'isDefault' => $language->isIsDefault(),
            'isEnabled' => $language->isIsEnabled(),
        ];
    }


    private function getEntityConfig(Request $request)
    {
        $entityName = $this->getEntityName($request);
        $entityClass = $this->getEntityClass($entityName);
        $config = $this->configurableAttributeReader->isConfigurable($entityClass);
        if ($config) {

            if (!$entityName) {
                throw $this->createNotFoundException('Entity name not provided');
            }

            $entity = $this->entityManager->getRepository($entityClass)->findOneBy([], ['id' => 'desc']);
            if ($entity === null) {
                $entity = new $entityClass();
                $this->entityManager->persist($entity);
                $this->entityManager->flush($entity);
                $entity = $this->entityManager->getRepository($entityClass)->findOneBy([], ['id' => 'desc']);

            }
            return $entity;
        }

        return false;
    }


    private function getEntityName(Request $request): string
    {
        $entityName = $request->query->get("entityName");
        if (!$entityName) {
            throw $this->createNotFoundException('Entity name not provided');
        }
        return ucfirst($entityName);
    }

    private function getEntityClass(string $entityName): string
    {
        $entityClass = "App\\Entity\\$entityName";
        if (!class_exists($entityClass)) {
            throw $this->createNotFoundException('Entity not found');
        }
        return $entityClass;
    }

    private function getFormClass(string $entityName): string
    {
        $formClass = "App\\Form\\$entityName" . "FormType";
        if (!class_exists($formClass)) {
            throw $this->createNotFoundException('Form not found');
        }
        return $formClass;
    }

    private function isTranslatableEntity(string $entityClass): bool
    {
        return !empty($this->annotationReader->getTranslatableField($entityClass));
    }

    private function handleTranslations($entity, string $entityName): void
    {
        $entityClass = get_class($entity);
        $entityTranslationClass = "App\\Entity\\" . $entityName . "Translation";

        if (!class_exists($entityTranslationClass)) {
            return;
        }

        $languages = $this->languageRepository->findByPublishNotDefault();
        $fields = $this->annotationReader->getTranslatableField($entityClass);

        foreach ($languages as $language) {
            foreach ($fields as $field) {
                $method = "get" . ucfirst($field);
                $content = $entity->$method();

                $translation = $this->entityManager->getRepository($entityTranslationClass)->findOneBy([
                    'locale' => $language->getCode(),
                    'field' => $field,
                    'object' => $entity,
                ]);

                if (!$translation) {
                    $translation = new $entityTranslationClass($language->getCode(), $field, $content);
                    $translation->setObject($entity);
                    $this->entityManager->persist($translation);
                    $this->entityManager->flush();
                } else {
                    $translation->setContent($content);
                }
            }
        }
    }


    private function handleDefaultEntity($entity, string $entityClass): void
    {
        if (method_exists($entity, 'isIsDefault') && $entity->isIsDefault()) {
            $repository = $this->entityManager->getRepository($entityClass);
            $defaultEntities = $repository->findBy(['isDefault' => true]);

            foreach ($defaultEntities as $defaultEntity) {
                if ($defaultEntity->getId() !== $entity->getId()) {
                    $defaultEntity->setIsDefault(false);
                    $this->entityManager->persist($defaultEntity);
                    $this->entityManager->flush();
                }
            }
        }
    }
}
