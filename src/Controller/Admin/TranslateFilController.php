<?php

namespace App\Controller\Admin;

use OpenAI;
use Exception;
use App\Service\Util;
use RuntimeException;
use App\Entity\Language;
use App\Service\ApiService;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;
use App\Service\TranslationManager;
use Symfony\Component\Intl\Languages;
use App\Service\DefaultLanguageService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\DemandeVisaRepository;
use App\Repository\NotificationRepository;
use Symfony\Component\Filesystem\Filesystem;

use Symfony\Component\HttpFoundation\Request;

use App\Repository\DemandePasseportRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\DemandePermisConduireRepository;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route(
    path: '/admin/translate_file',
    name: 'app_admin_'
)]
#[IsGranted('ROLE_ADMIN')]
class TranslateFilController extends AbstractController
{

    public function __construct(
        private TranslatorInterface $translator,
        private Util $util,
        private UrlGeneratorInterface $urlGenerator,
        private NotificationRepository $notificationRepository,
        private KernelInterface $kernel,
        private ParameterBagInterface $parameterBag,
        private EntityManagerInterface $entityManager,
        private TranslationManager $translationManager,
        private DefaultLanguageService $defaultLanguage,
        private ApiService $apiService
    ) {
    }



    #[Route(path: '/lang_generator', name: 'lang_generator')]
    public function lang_generator(Request $request, ApiService $apiService)
    {
        // Get the list of supported languages from the API
        $languages = $apiService->languages();

        // Generate the choices for the local_language field
        $choices = array_combine(array_column($languages, 'name'), array_column($languages, 'code'));

        // Créer le formulaire
        $form = $this->createFormBuilder()
            ->add('base_language', ChoiceType::class, [
                'choices' => $this->util->getLocalesName(),
                'label' => 'Choose base language:',
            ])
            ->add('local_language', ChoiceType::class, [
                'choices' => $choices,
                'label' => 'Enter local language:',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Submit',
            ])
            ->getForm();

        return $this->render('@admin/locale.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/lang_generator_submit', name: 'lang_generator_submit', methods: ['POST'])]
    public function lang_generator_submit(Request $request): JsonResponse
    {

        $data = json_decode($request->getContent(), true);
        $base_language = $data['base_language'];
       
        $array = Yaml::parseFile($this->kernel->getProjectDir() . '/translations/messages.' . $base_language . '.yaml');

        return new JsonResponse($array);
    }

    #[Route(path: '/create_yaml_file', name: 'create_yaml_file', methods: ['POST'])]
    public function create_yaml_file(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $local_language = $data['local_language'];
        $translations = $data['data'];

       // dd($local_language ,$translations);
    
        // Create the YAML file
        $yaml = Yaml::dump($translations, 2, 2);
        $yaml_file = $this->kernel->getProjectDir() . '/translations/messages.' . $local_language . '.yaml';
    
        // Use the Filesystem Component to write the YAML file
        $filesystem = new Filesystem();
        $filesystem->dumpFile($yaml_file, $yaml);
    
        return new JsonResponse(['success' => true]);
    }



    public function lang_load($base_lang, $local_lang, $local_lang_name)
    {
        $projectDir = $this->kernel->getProjectDir();

        $array = Yaml::parseFile($projectDir . '/translations/messages.' . $base_lang . '.yaml');


        $data = $this->traverse_array($array, $local_lang_name);
        // dd($this->traverse_array($array, "es"));

        $yaml = Yaml::dump($data, 2, 2);

        //  dd($yaml,$local_lang);

        // Écriture dans un fichier
        $yaml_new = file_put_contents($projectDir . '/translations/messages.' . $local_lang . '.yaml', $yaml);

        $array_fil = Yaml::parse($yaml);
    }

    private function traverse_array(array $array, string $locale): array
    {
        $translatedArray = [];

        // Traverse the array recursively and translate the values.
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                // If the value is itself an array, we call the function recursively
                $translatedArray[$key] = $this->traverse_array($value, $locale);
            } else {
                $translatedValue = $this->translateText($value, $locale);
                $translatedArray[$key] = trim($translatedValue);
            }
        }

        return $translatedArray;
    }

    #[Route(path: '/translate_text', name: 'translate_text', methods: ['POST'])]
    public function translateText(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $text = $data['text'];
        $locale = $data['locale'];
        $source = $data['source'];
    
        if (empty($text) || empty($locale) || empty($source)) {
            throw new InvalidArgumentException("Arguments 'text' and 'locale' are required.");
        }
    
        $data = [
            'q' => $text,
            'source' => $source,
            'target' => $locale,
        ];
        $translatedText = $this->apiService->translate($data);
        return new JsonResponse($translatedText);
    }

    
    

    private function traverseArrayForm(array $array, string $currentKey = ''): string
    {
        $form = '';
        foreach ($array as $key => $value) {
            $keyPath = $currentKey ? $currentKey . '_' . $key : $key;
            if (is_array($value)) {
                $form .= $this->traverseArrayForm($value, $keyPath);
            } else {
                $label = "<label for='$keyPath'>$keyPath</label>";
                $textarea = "<textarea class='form-control mytextarea' id='$keyPath' name='$keyPath' rows='4'>$value</textarea>";
                $form .= "<div class='form-group'>$label$textarea</div>";
            }
        }
        return $form;
    }

  
    #[Route('/lang_editor', name: 'lang_editor', methods: ['POST', 'GET'])]
    public function langEditor(Request $request): Response
    {
        $locale = $request->query->get('lang_load');
        $defaultLanguage = $this->defaultLanguage->getDefaultLanguageCode();

        $form = '';

        if (!empty($locale)) {
            // dd('fff');
            $this->translationManager->createTranslationFileIfNotExists($locale, $defaultLanguage);
            $yamlFilePath = $this->translationManager->getTranslationFilePath($locale);

            $array = $this->translationManager->parseTranslationFile($yamlFilePath);
            $form = $this->traverseArrayForm($array);

            if ($request->isMethod('POST')) {
                $data = $request->request->all();
                $data_new = [];

                foreach ($data as $key => $value) {
                    $chain = str_replace('_', '.', $key);
                    $data_new[$chain] = $value;
                }

                $this->translationManager->updateTranslationFile($yamlFilePath, $data_new);
                $this->addFlash('success', 'Langue mise à jour avec succès');
            }
        }

        return $this->render('@admin/lang_editor.html.twig', [
            'form' => $form,
            'locales' => $this->getLocales(),
            'lang_load' => $locale,
            'default_lang' => $defaultLanguage
        ]);
    }

    #[Route(path: '/lang_delete', name: 'lang_delete')]
    public function lang_delete(Request $request)
    {
        $locale = $request->query->get('lang_load');
        $projectDir = $this->kernel->getProjectDir();
        $yamlFilePath = sprintf('%s/translations/messages.%s.yaml', $projectDir, $locale);
        $filesystem = new Filesystem();
        $filesystem->remove($yamlFilePath);
        $this->addFlash('success', 'Langue supprimer avec succès');

        return $this->redirectToRoute('app_lang_editor', ['lang_load' => $locale]);
    }

    private function getLocales(): array
    {
        $locales = [];
        $languages = $this->entityManager->getRepository(Language::class)->findAll();

        foreach ($languages as $language) {
            $locales[$language->getCode()] = $language->getName();
        }

        return $locales;
    }
}
