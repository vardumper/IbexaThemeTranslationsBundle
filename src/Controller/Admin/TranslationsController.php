<?php

declare(strict_types=1);

namespace vardumper\IbexaThemeTranslationsBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Ibexa\Contracts\AdminUi\Controller\Controller;
use Ibexa\Contracts\Core\Repository\LanguageService;
use League\Csv\Reader;
use League\Csv\Writer;
use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheWarmer;
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
    private const CSRF_INLINE_SAVE = 'translations_inline_save';
    private const CSRF_DEEPL_TRANSLATE = 'translations_deepl_translate';
    private const CSRF_DRAFT_ACCEPT = 'translations_draft_accept';
    private const CSRF_DRAFT_REVERT = 'translations_draft_revert';
    private const CSRF_DELETE = 'translations_delete';

    /** Flush chunk size for bulk imports. */
    private const IMPORT_FLUSH_CHUNK = 500;

    public function __construct(
        private readonly TranslationRepository $translationRepository,
        private readonly TranslationDraftRepository $translationDraftRepository,
        private readonly FormFactoryInterface $formFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly LanguageResolverInterface $languageResolver,
        private readonly LanguageService $languageService,
        private readonly DeeplTranslationService $deeplTranslationService,
        private readonly TranslationCacheWarmer $cacheWarmer,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function listAction(Request $request, $page = 1): Response
    {
        $vals = $request->query->all('translations_filter');
        $sortBy = (string) $request->query->get('sort_by', 'id');
        $sortDir = (string) $request->query->get('sort_dir', 'ASC');
        $filterForm = $this->formFactory->createNamed('translations_filter', TranslationFilterType::class, [
            'languageCode' => $vals['languageCode'] ?? '',
            'status' => $vals['status'] ?? '',
            'search' => $vals['search'] ?? '',
            'perPage' => $vals['perPage'] ?? '25',
        ], [
            'method' => Request::METHOD_GET,
            'csrf_protection' => false,
        ]);

        // Paginate at the SQL level instead of loading every matching row into memory.
        $adapter = new class(
            $this->translationRepository,
            (string) ($vals['languageCode'] ?? ''),
            (string) ($vals['status'] ?? ''),
            (string) ($vals['search'] ?? ''),
            $sortBy,
            $sortDir,
        ) implements AdapterInterface {
            public function __construct(
                private readonly TranslationRepository $repository,
                private readonly string $languageCode,
                private readonly string $status,
                private readonly string $search,
                private readonly string $sortBy,
                private readonly string $sortDir,
            ) {
            }

            public function getNbResults(): int
            {
                return $this->repository->countByFilter(
                    $this->languageCode,
                    $this->status,
                    $this->search,
                    $this->sortBy,
                    $this->sortDir,
                );
            }

            public function getSlice(int $offset, int $length): iterable
            {
                return $this->repository->findPagedByFilter(
                    $this->languageCode,
                    $this->status,
                    $this->search,
                    $this->sortBy,
                    $this->sortDir,
                    $offset,
                    $length,
                );
            }
        };

        $currentPage = filter_var(
            $page,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ) ?: 1;

        $paginator = new Pagerfanta($adapter);
        $paginator->setMaxPerPage((int) ($vals['perPage'] ?? 25));
        $paginator->setCurrentPage($currentPage);

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

        $trans = $this->translationRepository->find($id);
        if ($trans === null) {
            return new Response('Translation not found', 404);
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
        if (!$this->isCsrfValid($request, self::CSRF_DEEPL_TRANSLATE)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

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

    public function acceptDraftAction(Request $request, int $id): Response
    {
        if (!$this->isCsrfValid($request, self::CSRF_DRAFT_ACCEPT)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $draft = $this->translationDraftRepository->find($id);
        if (!$draft) {
            return new JsonResponse([
                'error' => 'Draft not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Remove + upsert atomically so a failure cannot leave the draft deleted
        // without the translation being applied.
        $this->entityManager->beginTransaction();
        try {
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
            $this->entityManager->commit();
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            return new JsonResponse([
                'error' => 'Accepting the draft failed, please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'success' => true,
            'translation' => $translation,
        ]);
    }

    public function revertDraftAction(Request $request, int $id): Response
    {
        if (!$this->isCsrfValid($request, self::CSRF_DRAFT_REVERT)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

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
        if (!$this->isCsrfValid($request, self::CSRF_INLINE_SAVE)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode((string) $request->getContent(), true);
        $translation = \is_array($data) ? ($data['translation'] ?? null) : null;

        if (!\is_string($translation)) {
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

    public function deleteAction(Request $request, $id = null): Response
    {
        if ($id === null) {
            return new Response('No id provided', 404);
        }

        if (!$this->isCsrfValid($request, self::CSRF_DELETE)) {
            return new Response('Invalid CSRF token', 403);
        }

        $entity = $this->translationRepository->find($id);
        if ($entity === null) {
            return new Response('Translation not found', 404);
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $this->redirectToRoute('ibexa_theme_translations.list');
    }

    public function exportAction(): Response
    {
        // Stream rows instead of materializing the whole table in memory.
        $query = $this->translationRepository->createExportQuery();

        $fileName = sprintf('translation-export-%s.csv', date('Ymd-His'));

        return new StreamedResponse(
            static function () use ($query): void {
                $csv = Writer::createFromStream(fopen('php://output', 'wb'));
                $csv->setDelimiter(';');
                $csv->setOutputBOM(Reader::BOM_UTF8);
                $csv->insertOne(['id', 'transKey', 'languageCode', 'translation']);

                $writeRow = static function (array $row) use ($csv): void {
                    $csv->insertOne([
                        (string) ($row['id'] ?? ''),
                        (string) ($row['transKey'] ?? ''),
                        (string) ($row['languageCode'] ?? ''),
                        (string) ($row['translation'] ?? ''),
                    ]);
                };

                if (method_exists($query, 'onEachResult')) { /* Doctrine ORM 3.1+ */
                    $query->onEachResult(static function (array $row) use ($writeRow): void {
                        $writeRow($row);
                    });
                } else {
                    foreach ($query->iterate() as $row) {
                        if (\is_array($row)) {
                            $writeRow($row);
                        }
                    }
                }

                // No explicit flush needed: Symfony's StreamedResponse flushes php://output.
            },
            Response::HTTP_OK,
            [
                'Content-Type' => 'text/csv',
                'Cache-Control' => 'private',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]
        );
    }

    public function importAction(Request $request): Response
    {
        $form = $this->formFactory->createNamed(
            'translation_import',
            TranslationsImportType::class
        );

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $csvFile */
            $csvFile = $form->get('csv')->getData();
            $mode = (string) ($form->getData()['mode'] ?? '');

            // Detect the delimiter from the header line (robust for any file size, BOM-aware).
            $headerLine = '';
            $handle = fopen($csvFile->getPathname(), 'rb');
            if ($handle !== false) {
                $fetched = fgets($handle);
                if ($fetched !== false) {
                    $headerLine = $fetched;
                }
                fclose($handle);
            }
            $separator = str_contains(preg_replace('/^\xEF\xBB\xBF/', '', $headerLine), ';') ? ';' : ',';

            $reader = Reader::createFromPath($csvFile->getPathname(), 'r');
            $reader->setHeaderOffset(0);
            $reader->setDelimiter($separator);

            // Collect and validate all rows up front (the CSV's id column is an export
            // artifact and is intentionally ignored on import).
            $records = [];
            $skippedRows = [];
            $rowNumber = 0;
            foreach ($reader->getRecords() as $record) {
                $rowNumber++;
                if (!\is_array($record)) {
                    continue;
                }

                $transKey = trim((string) ($record['transKey'] ?? ''));
                $languageCode = trim((string) ($record['languageCode'] ?? ''));
                if ($transKey === '' || $languageCode === '') {
                    $skippedRows[] = "row {$rowNumber} (missing transKey or languageCode)";
                    continue;
                }

                $records[] = [
                    'transKey' => $transKey,
                    'languageCode' => $languageCode,
                    'translation' => isset($record['translation']) ? (string) $record['translation'] : null,
                ];
            }

            if ($mode === 'truncate') {
                // Bulk delete bypasses entity listeners — clear every tier explicitly so
                // languages absent from the CSV do not keep serving stale translations.
                $this->cacheWarmer->clearAll();
                $this->translationRepository->truncate();
            }

            // One query for all existing rows in scope instead of one per CSV row.
            $existingMap = [];
            if ($records !== []) {
                foreach (
                    $this->translationRepository->findByTransKeysAndLanguages(
                        array_values(array_unique(array_column($records, 'transKey'))),
                        array_values(array_unique(array_column($records, 'languageCode')))
                    ) as $existing
                ) {
                    $existingMap[$existing->getTransKey() . "\0" . $existing->getLanguageCode()] = $existing;
                }
            }

            $pendingFlushes = 0;
            foreach ($records as $record) {
                $pairId = $record['transKey'] . "\0" . $record['languageCode'];

                if (isset($existingMap[$pairId])) {
                    $entity = $existingMap[$pairId];
                    $entity->setTranslation($record['translation']);
                } else {
                    // Merge mode adds new keys (as the UI promises); truncate mode inserts everything fresh.
                    $entity = Translation::create($record['languageCode'], $record['transKey'], $record['translation']);
                }

                $this->entityManager->persist($entity);

                if (++$pendingFlushes >= self::IMPORT_FLUSH_CHUNK) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                    $pendingFlushes = 0;
                }
            }
            $this->entityManager->flush();
            $this->entityManager->clear();

            @unlink($csvFile->getPathname());

            if ($skippedRows !== []) {
                $this->addFlash('warning', sprintf(
                    'Import finished with %d skipped row(s): %s',
                    \count($skippedRows),
                    implode('; ', array_slice($skippedRows, 0, 5))
                ));
            } else {
                $this->addFlash('success', sprintf('Imported %d translation(s).', \count($records)));
            }

            return $this->redirectToRoute('ibexa_theme_translations.list');
        }

        return $this->render('@IbexaThemeTranslations/admin/translations/import.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Validates the CSRF token sent by the admin UI's fetch() calls.
     * The token arrives either as a "_token" field in a JSON body or as a regular POST parameter.
     */
    private function isCsrfValid(Request $request, string $tokenId): bool
    {
        $content = (string) $request->getContent();
        if ($content !== '' && str_starts_with(ltrim($content), '{')) {
            $data = json_decode($content, true);
            $token = \is_array($data) ? ($data['_token'] ?? null) : null;
        } else {
            $token = $request->request->get('_token');
        }

        return \is_string($token) && $this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $token));
    }
}
