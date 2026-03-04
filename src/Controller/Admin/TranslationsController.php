<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Ibexa\Contracts\AdminUi\Controller\Controller;
use Ibexa\Contracts\Core\Repository\LanguageService;
use League\Csv\Reader;
use League\Csv\Writer;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use vardumper\IbexaThemeTranslationsBundle\Entity\Translation;
use vardumper\IbexaThemeTranslationsBundle\Entity\TranslationDraft;
use vardumper\IbexaThemeTranslationsBundle\FieldType\Translation\Value;
use vardumper\IbexaThemeTranslationsBundle\Form\Type\TranslationFilterType;
use vardumper\IbexaThemeTranslationsBundle\Form\Type\TranslationsImportType;
use vardumper\IbexaThemeTranslationsBundle\Form\Type\TranslationType;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationDraftRepository;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationRepository;
use vardumper\IbexaThemeTranslationsBundle\Service\DeeplTranslationService;
use vardumper\IbexaThemeTranslationsBundle\Service\LanguageResolverInterface;

final class TranslationsController extends Controller
{
    public function __construct(
        private readonly TranslationRepository $translationRepository,
        private readonly TranslationDraftRepository $translationDraftRepository,
        private readonly FormFactoryInterface $formFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly LanguageResolverInterface $languageResolver,
        private readonly LanguageService $languageService,
        private readonly DeeplTranslationService $deeplTranslationService,
    ) {
    }

    public function listAction(Request $request, $page = 1): Response
    {
        $vals = $request->query->all('translations_filter');
        $sortBy = $request->query->get('sort_by', 'id');
        $sortDir = $request->query->get('sort_dir', 'ASC');
        $filterForm = $this->formFactory->createNamed('translations_filter', TranslationFilterType::class, [
            'languageCode' => $vals['languageCode'] ?? '',
            'status' => $vals['status'] ?? '',
            'search' => $vals['search'] ?? '',
            'perPage' => $vals['perPage'] ?? '25',
        ], [
            'method' => Request::METHOD_GET,
            'csrf_protection' => false,
        ]);

        $all = $this->translationRepository->findByFilter(
            $vals['languageCode'] ?? '',
            $vals['status'] ?? '',
            $vals['search'] ?? '',
            $sortBy,
            $sortDir,
        );

        $paginator = new Pagerfanta(new ArrayAdapter($all));
        $paginator->setMaxPerPage((int)($vals['perPage'] ?? 25));
        $paginator->setCurrentPage($page);

        $pageResults = iterator_to_array($paginator->getCurrentPageResults());
        $transKeys = array_unique(array_map(fn (Translation $t) => $t->getTransKey(), $pageResults));
        $draftsMap = $this->translationDraftRepository->findIndexedByTransKey($transKeys);

        return $this->render('@IbexaThemeTranslations/admin/translations/list.html.twig', [
            'totalCount' => $paginator->getNbResults(),
            'translations' => $paginator,
            'form' => $filterForm->createView(),
            'activeLanguages' => array_map(
                static fn ($lang) => $lang->languageCode,
                array_filter($this->languageService->loadLanguages(), static fn ($lang) => $lang->enabled)
            ),
            'draftsMap' => $draftsMap,
            'deeplConfigured' => $this->deeplTranslationService->isConfigured(),
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    public function createAction(Request $request): Response
    {
        $createForm = $this->formFactory->createNamed(
            'translation_create',
            TranslationType::class
        );
        $createForm->add('save', SubmitType::class, [
            'label' => 'Create Translation',
        ]);

        $createForm->handleRequest($request);
        if ($createForm->isSubmitted() && $createForm->isValid()) {
            $translationData = $createForm->getData();
            $entity = Translation::fromFormData($translationData);
            $this->entityManager->persist($entity);
            $this->entityManager->flush();

            $autoTranslate = $request->request->getBoolean('auto_translate_deepl');
            if ($autoTranslate && $this->deeplTranslationService->isConfigured() && !empty($entity->getTranslation())) {
                $targetLanguages = $this->languageResolver->getUsedLanguages();
                $draftCount = 0;
                $errors = [];
                foreach ($targetLanguages as $targetLang) {
                    if ($targetLang === $entity->getLanguageCode()) {
                        continue;
                    }

                    try {
                        $translated = $this->deeplTranslationService->translate(
                            $entity->getTranslation(),
                            $entity->getLanguageCode(),
                            $targetLang
                        );
                        $draft = $this->translationDraftRepository->findOneByKeyAndLanguage(
                            $entity->getTransKey(),
                            $targetLang
                        );
                        if ($draft === null) {
                            $draft = new TranslationDraft($targetLang, $entity->getTransKey(), $translated);
                        } else {
                            $draft->setTranslation($translated);
                        }
                        $this->entityManager->persist($draft);
                        $draftCount++;
                    } catch (\Throwable $e) {
                        $errors[] = $targetLang . ': ' . $e->getMessage();
                    }
                }
                $this->entityManager->flush();

                if ($draftCount > 0) {
                    $this->addFlash('success', sprintf(
                        'Automated translation created %d draft(s) for "%s" — pending approval.',
                        $draftCount,
                        $entity->getTransKey()
                    ));
                }
                foreach ($errors as $error) {
                    $this->addFlash('warning', 'Automated translation error — ' . $error);
                }
            } elseif ($autoTranslate && !$this->deeplTranslationService->isConfigured()) {
                $this->addFlash('warning', 'Automated Translations service is not configured.');
            } elseif ($autoTranslate && empty($entity->getTranslation())) {
                $this->addFlash('warning', 'Source translation is empty — cannot auto-translate.');
            }

            $this->entityManager->clear();

            return $this->redirectToRoute('ibexa_theme_translations.list');
        }

        return $this->render('@IbexaThemeTranslations/admin/translations/create.html.twig', [
            'form' => $createForm->createView(),
            'deeplConfigured' => $this->deeplTranslationService->isConfigured(),
        ]);
    }

    public function editAction(Request $request, $id = null): Response
    {
        if ($id === null) {
            return new Response('No id provided', 404);
        }

        $editForm = $this->formFactory->createNamed(
            'translation_edit',
            TranslationType::class
        );

        $editForm->add(
            'id',
            HiddenType::class,
            [
                'data' => $id,
            ]
        );
        $editForm->add('save', SubmitType::class, [
            'label' => 'Save Changes',
        ]);

        $trans = $this->translationRepository->find($id);

        if ($request->isMethod('POST')) {
            $editForm->handleRequest($request);
            if ($editForm->isSubmitted() && $editForm->isValid()) {
                $trans->setTranslation($editForm->getData()->getTranslation());
                $this->entityManager->persist($trans);
                $this->entityManager->flush();
                $this->entityManager->clear();

                return $this->redirectToRoute('ibexa_theme_translations.list');
            }

            return $this->render('@IbexaThemeTranslations/admin/translations/edit.html.twig', [
                'form' => $editForm->createView(),
                'translation' => $trans,
                'draft' => null,
                'deeplConfigured' => $this->deeplTranslationService->isConfigured(),
            ]);
        }

        $draft = $this->translationDraftRepository->findOneByKeyAndLanguage(
            $trans->getTransKey(),
            $trans->getLanguageCode()
        );

        $data = new Value();
        $data->setLanguageCode($trans->getLanguageCode());
        $data->setTransKey($trans->getTransKey());
        $data->setTranslation($trans->getTranslation());
        $editForm->setData($data);

        return $this->render('@IbexaThemeTranslations/admin/translations/edit.html.twig', [
            'form' => $editForm->createView(),
            'translation' => $trans,
            'draft' => $draft,
            'deeplConfigured' => $this->deeplTranslationService->isConfigured(),
        ]);
    }

    public function deeplTranslateAction(Request $request, int $id): Response
    {
        if (!$this->deeplTranslationService->isConfigured()) {
            return new JsonResponse([
                'error' => 'DeepL is not configured',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $entity = $this->translationRepository->find($id);
        if (!$entity) {
            return new JsonResponse([
                'error' => 'Translation not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $allForKey = $this->translationRepository->findByTransKey($entity->getTransKey());
        $sourceEntity = null;
        foreach ($allForKey as $candidate) {
            if ($candidate->getLanguageCode() !== $entity->getLanguageCode() && !empty($candidate->getTranslation())) {
                $sourceEntity = $candidate;
                break;
            }
        }

        if ($sourceEntity === null) {
            return new JsonResponse(
                [
                    'error' => 'No source translation found for this key. At least one other language must have a translation.',
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $translated = $this->deeplTranslationService->translate(
                $sourceEntity->getTranslation(),
                $sourceEntity->getLanguageCode(),
                $entity->getLanguageCode()
            );
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'DeepL translation failed: ' . $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        $draft = $this->translationDraftRepository->findOneByKeyAndLanguage(
            $entity->getTransKey(),
            $entity->getLanguageCode()
        );
        if ($draft === null) {
            $draft = new TranslationDraft($entity->getLanguageCode(), $entity->getTransKey(), $translated);
        } else {
            $draft->setTranslation($translated);
        }
        $this->entityManager->persist($draft);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'draftId' => $draft->getId(),
            'draftTranslation' => $draft->getTranslation(),
        ]);
    }

    public function acceptDraftAction(int $id): Response
    {
        $draft = $this->translationDraftRepository->find($id);
        if (!$draft) {
            return new JsonResponse([
                'error' => 'Draft not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $entity = $this->translationRepository->findOneBy([
            'transKey' => $draft->getTransKey(),
            'languageCode' => $draft->getLanguageCode(),
        ]);
        if ($entity === null) {
            $entity = new Translation($draft->getLanguageCode(), $draft->getTransKey(), $draft->getTranslation());
        } else {
            $entity->setTranslation($draft->getTranslation());
        }

        $translation = $draft->getTranslation();

        $this->entityManager->persist($entity);
        $this->entityManager->remove($draft);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'translation' => $translation,
        ]);
    }

    public function revertDraftAction(int $id): Response
    {
        $draft = $this->translationDraftRepository->find($id);
        if (!$draft) {
            return new JsonResponse([
                'error' => 'Draft not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($draft);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
        ]);
    }

    public function inlineSaveAction(Request $request, int $id): Response
    {
        $data = json_decode($request->getContent(), true);
        $translation = $data['translation'] ?? null;

        if ($translation === null) {
            return new JsonResponse([
                'error' => 'No translation provided',
            ], Response::HTTP_BAD_REQUEST);
        }

        $entity = $this->translationRepository->find($id);
        if (!$entity) {
            return new JsonResponse([
                'error' => 'Translation not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $entity->setTranslation($translation);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
        ]);
    }

    public function deleteAction($id = null): Response
    {
        if ($id === null) {
            return new Response('No id provided', 404);
        }

        $entity = $this->translationRepository->find($id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $this->redirectToRoute('ibexa_theme_translations.list');
    }

    public function exportAction(): Response
    {
        $translations = $this->translationRepository->findAll();
        $fileName = sprintf('translation-export-%s.csv', time());
        $csv = Writer::createFromString();
        $csv->setDelimiter(';');
        $csv->setOutputBOM(Reader::BOM_UTF8);
        $csv->insertOne(['id', 'transKey', 'languageCode', 'translation']);
        $records = [];
        foreach ($translations as $translation) {
            $records[] = $translation->jsonSerialize();
        }
        $csv->insertAll($records);
        $response = new Response();
        $response->headers->set('Content-type', 'text/csv');
        $response->headers->set('Cache-Control', 'private');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '";');
        $response->setContent($csv->toString());

        return $response;
    }

    public function importAction(Request $request): Response
    {
        $form = $this->formFactory->createNamed(
            'translation_import',
            TranslationsImportType::class
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $mode = $form->getData()['mode'];

            if ($mode === 'truncate') {
                $this->translationRepository->truncate();
            }

            /** @var UploadedFile $csvFile */
            $csvFile = $form->get('csv')->getData();
            $reader = Reader::createFromPath($csvFile->getPathname(), 'r');

            $tmp = new \SplFileObject($csvFile->getPathname());
            $tmp->seek(2);
            $line = $tmp->current();
            $separator = \str_contains($line, ';') ? ';' : ',';
            $tmp = null;

            $reader->setHeaderOffset(0);
            $reader->setDelimiter($separator);
            $records = $reader->getRecords();

            foreach ($records as $record) {
                if ($mode === 'merge') {
                    try {
                        $entity = $this->translationRepository->findOneBy([
                            'transKey' => $record['transKey'],
                            'languageCode' => $record['languageCode'],
                        ]);
                    } catch (\Exception $e) {
                        $entity = null;
                    }
                    if ($entity === null) {
                        continue;
                    }
                    $entity->setTranslation($record['translation']);
                    $entity->setTransKey($record['transKey']);
                    $entity->setLanguageCode($record['languageCode']);
                } else {
                    $entity = Translation::fromArray($record);
                }
                $this->entityManager->persist($entity);
            }
            $this->entityManager->flush();
            $this->entityManager->clear();
            \unlink($csvFile->getPathname());

            return $this->redirectToRoute('ibexa_theme_translations.list');
        }

        return $this->render('@IbexaThemeTranslations/admin/translations/import.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
