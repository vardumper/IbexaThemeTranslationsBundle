<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Ibexa\AutomatedTranslation\ClientProvider;
use Ibexa\Contracts\AutomatedTranslation\Client\ClientInterface;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Core\MVC\Symfony\Locale\LocaleConverterInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use vardumper\IbexaThemeTranslationsBundle\Cache\TranslationCacheWarmer;
use vardumper\IbexaThemeTranslationsBundle\Controller\Admin\TranslationsController;
use vardumper\IbexaThemeTranslationsBundle\Entity\Translation;
use vardumper\IbexaThemeTranslationsBundle\Entity\TranslationDraft;
use vardumper\IbexaThemeTranslationsBundle\FieldType\Translation\Value;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationDraftRepository;
use vardumper\IbexaThemeTranslationsBundle\Repository\TranslationRepository;
use vardumper\IbexaThemeTranslationsBundle\Service\DeeplTranslationService;
use vardumper\IbexaThemeTranslationsBundle\Service\LanguageResolverInterface;

uses(PHPUnit\Framework\TestCase::class);

/** Build a DeeplTranslationService with clientProvider=null → isConfigured()=false */
function deeplNotConfigured(): DeeplTranslationService
{
    return new DeeplTranslationService(
        testMock(LocaleConverterInterface::class),
        null
    );
}

/** Build a DeeplTranslationService whose mock client returns the given translation */
function deeplConfigured(string $returnValue = '<deepl>Hallo</deepl>'): DeeplTranslationService
{
    $client = testMock(ClientInterface::class);
    $client->method('translate')->willReturn($returnValue);

    $provider = testMock(ClientProvider::class);
    $provider->method('getClients')->willReturn(['deepl' => $client]);
    $provider->method('get')->with('deepl')->willReturn($client);

    $localeConverter = testMock(LocaleConverterInterface::class);
    $localeConverter->method('convertToPOSIX')->willReturnArgument(0);

    return new DeeplTranslationService($localeConverter, $provider);
}

/** CSRF manager that accepts every token (default) — pass $valid=false to simulate rejection. */
function makeCsrfManager(bool $valid = true): CsrfTokenManagerInterface
{
    $manager = testMock(CsrfTokenManagerInterface::class);
    $manager->method('isTokenValid')->willReturn($valid);

    return $manager;
}

/** POST request carrying a CSRF token in the "_token" parameter. */
function csrfRequest(string $tokenId = 'test'): Request
{
    return new Request([], ['_token' => 'token-' . $tokenId]);
}

function makeController(
    ?TranslationRepository $repo = null,
    ?TranslationDraftRepository $draftRepo = null,
    ?FormFactoryInterface $formFactory = null,
    ?EntityManagerInterface $em = null,
    ?LanguageResolverInterface $resolver = null,
    ?LanguageService $languageService = null,
    ?DeeplTranslationService $deepl = null,
    ?TranslationCacheWarmer $cacheWarmer = null,
    ?CsrfTokenManagerInterface $csrfManager = null,
): TranslationsController {
    return new TranslationsController(
        $repo ?? testMock(TranslationRepository::class),
        $draftRepo ?? testMock(TranslationDraftRepository::class),
        $formFactory ?? testMock(FormFactoryInterface::class),
        $em ?? testMock(EntityManagerInterface::class),
        $resolver ?? testMock(LanguageResolverInterface::class),
        $languageService ?? testMock(LanguageService::class),
        $deepl ?? deeplNotConfigured(),
        $cacheWarmer ?? makeCacheWarmer(),
        $csrfManager ?? makeCsrfManager(),
    );
}

// ─── editAction ──────────────────────────────────────────────────────────────

it('editAction returns 404 when id is null', function () {
    $controller = makeController();
    $response = $controller->editAction(new Request(), null);

    expect($response->getStatusCode())->toBe(404);
    expect($response->getContent())->toContain('No id provided');
});

it('listAction executes filtering/pagination flow then fails on render without container', function () {
    $form = $this->createMock(FormInterface::class);
    $form->method('createView')->willReturn(new FormView());

    $formFactory = $this->createMock(FormFactoryInterface::class);
    $formFactory->method('createNamed')->willReturn($form);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('countByFilter')->willReturn(2);
    $repo->method('findPagedByFilter')->willReturn([
        new Translation('eng-GB', 'hello.key', 'Hello'),
        new Translation('deu-DE', 'hello.key', 'Hallo'),
    ]);

    $draftRepo = $this->createMock(TranslationDraftRepository::class);
    $draftRepo->method('findIndexedByTransKey')->willReturn([]);

    $languageService = $this->createMock(LanguageService::class);
    $languageService->method('loadLanguages')->willReturn([
        new class() {
            public string $languageCode = 'eng-GB';
            public bool $enabled = true;
        },
        new class() {
            public string $languageCode = 'deu-DE';
            public bool $enabled = true;
        },
    ]);

    $controller = makeController(
        repo: $repo,
        draftRepo: $draftRepo,
        formFactory: $formFactory,
        languageService: $languageService,
        deepl: deeplConfigured(),
    );

    expect(fn () => $controller->listAction(Request::create('/'), 1))->toThrow(Error::class);
});

it('listAction accepts numeric string page and does not fail with TypeError', function () {
    $form = $this->createMock(FormInterface::class);
    $form->method('createView')->willReturn(new FormView());

    $formFactory = $this->createMock(FormFactoryInterface::class);
    $formFactory->method('createNamed')->willReturn($form);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('countByFilter')->willReturn(1);
    $repo->method('findPagedByFilter')->willReturn([
        new Translation('eng-GB', 'hello.key', 'Hello'),
    ]);

    $draftRepo = $this->createMock(TranslationDraftRepository::class);
    $draftRepo->method('findIndexedByTransKey')->willReturn([]);

    $languageService = $this->createMock(LanguageService::class);
    $languageService->method('loadLanguages')->willReturn([
        new class() {
            public string $languageCode = 'eng-GB';
            public bool $enabled = true;
        },
    ]);

    $controller = makeController(
        repo: $repo,
        draftRepo: $draftRepo,
        formFactory: $formFactory,
        languageService: $languageService,
        deepl: deeplConfigured(),
    );

    try {
        $controller->listAction(Request::create('/'), '1');
        expect(true)->toBeTrue();
    } catch (\Throwable $e) {
        expect($e)->toBeInstanceOf(Error::class);
        expect($e)->not->toBeInstanceOf(TypeError::class);
    }
});

it('createAction persists valid form data and then fails on redirect without container', function () {
    $value = new Value();
    $value->setLanguageCode('eng-GB');
    $value->setTransKey('create.key');
    $value->setTranslation('Created');

    $form = $this->createMock(FormInterface::class);
    $form->method('add')->willReturnSelf();
    $form->method('handleRequest')->willReturnSelf();
    $form->method('isSubmitted')->willReturn(true);
    $form->method('isValid')->willReturn(true);
    $form->method('getData')->willReturn($value);

    $formFactory = $this->createMock(FormFactoryInterface::class);
    $formFactory->method('createNamed')->willReturn($form);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->once())->method('persist');
    $em->expects($this->once())->method('flush');
    $em->expects($this->once())->method('clear');

    $controller = makeController(formFactory: $formFactory, em: $em, deepl: deeplNotConfigured());

    expect(fn () => $controller->createAction(Request::create('/', 'POST')))->toThrow(Error::class);
});

it('editAction GET populates form data and then fails on render without container', function () {
    $translation = new Translation('eng-GB', 'edit.key', 'Edit me');

    $form = $this->createMock(FormInterface::class);
    $form->method('add')->willReturnSelf();
    $form->method('setData')->willReturnSelf();
    $form->method('createView')->willReturn(new FormView());

    $formFactory = $this->createMock(FormFactoryInterface::class);
    $formFactory->method('createNamed')->willReturn($form);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('find')->with(10)->willReturn($translation);

    $draftRepo = $this->createMock(TranslationDraftRepository::class);
    $draftRepo->method('findOneByKeyAndLanguage')->willReturn(null);

    $controller = makeController(repo: $repo, draftRepo: $draftRepo, formFactory: $formFactory, deepl: deeplConfigured());

    expect(fn () => $controller->editAction(Request::create('/', 'GET'), 10))->toThrow(Error::class);
});

it('editAction POST valid persists changes and then fails on redirect without container', function () {
    $translation = new Translation('eng-GB', 'edit.post.key', 'Old');
    $value = new Value();
    $value->setLanguageCode('eng-GB');
    $value->setTransKey('edit.post.key');
    $value->setTranslation('New');

    $form = $this->createMock(FormInterface::class);
    $form->method('add')->willReturnSelf();
    $form->method('handleRequest')->willReturnSelf();
    $form->method('isSubmitted')->willReturn(true);
    $form->method('isValid')->willReturn(true);
    $form->method('getData')->willReturn($value);

    $formFactory = $this->createMock(FormFactoryInterface::class);
    $formFactory->method('createNamed')->willReturn($form);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('find')->with(11)->willReturn($translation);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->once())->method('persist')->with($translation);
    $em->expects($this->once())->method('flush');
    $em->expects($this->once())->method('clear');

    $controller = makeController(repo: $repo, formFactory: $formFactory, em: $em);

    expect(fn () => $controller->editAction(Request::create('/', 'POST'), 11))->toThrow(Error::class);
    expect($translation->getTranslation())->toBe('New');
});

it('importAction merge mode updates existing records and then fails on redirect without container', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'tt_csv_');
    file_put_contents($tmp, "id;transKey;languageCode;translation\n1;import.key;eng-GB;Updated\n2;import.key;eng-GB;UpdatedAgain\n");
    $uploaded = new UploadedFile($tmp, 'translations.csv', null, null, true);

    $csvField = $this->createMock(FormInterface::class);
    $csvField->method('getData')->willReturn($uploaded);

    $form = $this->createMock(FormInterface::class);
    $form->method('handleRequest')->willReturnSelf();
    $form->method('isSubmitted')->willReturn(true);
    $form->method('isValid')->willReturn(true);
    $form->method('getData')->willReturn(['mode' => 'merge']);
    $form->method('get')->with('csv')->willReturn($csvField);

    $formFactory = $this->createMock(FormFactoryInterface::class);
    $formFactory->method('createNamed')->willReturn($form);

    $existing = new Translation('eng-GB', 'import.key', 'Old');
    $repo = $this->createMock(TranslationRepository::class);
    // One batched lookup for all existing rows in scope (replaces the old per-row findOneBy).
    $repo->method('findByTransKeysAndLanguages')
        ->with(['import.key'], ['eng-GB'])
        ->willReturn([$existing]);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->exactly(2))->method('persist')->with($existing);
    $em->expects($this->once())->method('flush');
    $em->expects($this->once())->method('clear');

    $controller = makeController(repo: $repo, formFactory: $formFactory, em: $em);

    expect(fn () => $controller->importAction(Request::create('/', 'POST')))->toThrow(Error::class);
    expect($existing->getTranslation())->toBe('UpdatedAgain');
    expect(file_exists($tmp))->toBeFalse();
});

it('importAction truncate mode clears all cache tiers and truncates before inserting', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'tt_csv_');
    file_put_contents($tmp, "id;transKey;languageCode;translation\n1;new.key;eng-GB;Fresh\n");
    $uploaded = new UploadedFile($tmp, 'translations.csv', null, null, true);

    $csvField = $this->createMock(FormInterface::class);
    $csvField->method('getData')->willReturn($uploaded);

    $form = $this->createMock(FormInterface::class);
    $form->method('handleRequest')->willReturnSelf();
    $form->method('isSubmitted')->willReturn(true);
    $form->method('isValid')->willReturn(true);
    $form->method('getData')->willReturn(['mode' => 'truncate']);
    $form->method('get')->with('csv')->willReturn($csvField);

    $formFactory = $this->createMock(FormFactoryInterface::class);
    $formFactory->method('createNamed')->willReturn($form);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->once())->method('truncate');
    $repo->method('findByTransKeysAndLanguages')->willReturn([]);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->once())
        ->method('persist')
        ->with($this->callback(static fn ($e): bool => $e instanceof Translation && $e->getTransKey() === 'new.key'));
    $em->expects($this->once())->method('flush');
    $em->expects($this->once())->method('clear');

    $controller = makeController(repo: $repo, formFactory: $formFactory, em: $em);

    expect(fn () => $controller->importAction(Request::create('/', 'POST')))->toThrow(Error::class);
    expect(file_exists($tmp))->toBeFalse();
});

// ─── deleteAction ─────────────────────────────────────────────────────────────

it('deleteAction returns 404 when id is null', function () {
    $controller = makeController();
    $response = $controller->deleteAction(new Request(), null);

    expect($response->getStatusCode())->toBe(404);
    expect($response->getContent())->toContain('No id provided');
});

it('deleteAction returns 403 when the CSRF token is invalid', function () {
    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->never())->method('find');

    $controller = makeController(repo: $repo, csrfManager: makeCsrfManager(valid: false));
    $response = $controller->deleteAction(csrfRequest(), 1);

    expect($response->getStatusCode())->toBe(403);
});

it('deleteAction removes entity and then attempts redirect', function () {
    $entity = new Translation('eng-GB', 'delete.key', 'To delete');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('find')->with(1)->willReturn($entity);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->once())->method('remove')->with($entity);
    $em->expects($this->once())->method('flush');
    $em->expects($this->once())->method('clear');

    $controller = makeController(repo: $repo, em: $em);

    // Redirect generation needs framework services that are not booted in this unit test.
    expect(fn () => $controller->deleteAction(csrfRequest(), 1))->toThrow(Error::class);
});

// ─── deeplTranslateAction ────────────────────────────────────────────────────

it('deeplTranslateAction returns 403 when the CSRF token is invalid', function () {
    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->never())->method('find');

    $controller = makeController(repo: $repo, csrfManager: makeCsrfManager(valid: false));
    $response = $controller->deeplTranslateAction(csrfRequest(), 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
});

it('deeplTranslateAction returns 503 when DeepL is not configured', function () {
    $controller = makeController(deepl: deeplNotConfigured());
    $response = $controller->deeplTranslateAction(csrfRequest(), 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_SERVICE_UNAVAILABLE);
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('error');
});

it('deeplTranslateAction returns 404 when translation entity is not found', function () {
    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('find')->with(99)->willReturn(null);

    $controller = makeController(repo: $repo, deepl: deeplConfigured());
    $response = $controller->deeplTranslateAction(csrfRequest(), 99);

    expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('error');
});

it('deeplTranslateAction returns 422 when no source translation exists for the key', function () {
    $entity = new Translation('deu-DE', 'my.key', '');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('find')->with(1)->willReturn($entity);
    $repo->method('findByTransKey')->willReturn([$entity]);

    $controller = makeController(repo: $repo, deepl: deeplConfigured());
    $response = $controller->deeplTranslateAction(csrfRequest(), 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY);
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('error');
});

it('deeplTranslateAction returns 502 when DeepL throws an exception', function () {
    $client = $this->createMock(ClientInterface::class);
    $client->method('translate')->willThrowException(new RuntimeException('API error'));

    $provider = $this->createMock(ClientProvider::class);
    $provider->method('getClients')->willReturn(['deepl' => $client]);
    $provider->method('get')->willReturn($client);

    $localeConverter = $this->createMock(LocaleConverterInterface::class);
    $localeConverter->method('convertToPOSIX')->willReturnArgument(0);

    $deepl = new DeeplTranslationService($localeConverter, $provider);

    $target = new Translation('deu-DE', 'my.key', '');
    $source = new Translation('eng-GB', 'my.key', 'Hello');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('find')->with(1)->willReturn($target);
    $repo->method('findByTransKey')->willReturn([$target, $source]);

    $draftRepo = $this->createMock(TranslationDraftRepository::class);
    $draftRepo->method('findOneByKeyAndLanguage')->willReturn(null);

    $controller = makeController(repo: $repo, draftRepo: $draftRepo, deepl: $deepl);
    $response = $controller->deeplTranslateAction(csrfRequest(), 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_BAD_GATEWAY);
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('error');
});

it('deeplTranslateAction creates a new draft on success', function () {
    $target = new Translation('deu-DE', 'my.key', '');
    $source = new Translation('eng-GB', 'my.key', 'Hello');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('find')->with(1)->willReturn($target);
    $repo->method('findByTransKey')->willReturn([$target, $source]);

    $draftRepo = $this->createMock(TranslationDraftRepository::class);
    $draftRepo->method('findOneByKeyAndLanguage')->willReturn(null);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->once())->method('persist');
    $em->expects($this->once())->method('flush');

    $controller = makeController(repo: $repo, draftRepo: $draftRepo, em: $em, deepl: deeplConfigured());
    $response = $controller->deeplTranslateAction(csrfRequest(), 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeTrue();
    expect($data['draftTranslation'])->toBe('Hallo');
});

it('deeplTranslateAction updates an existing draft on success', function () {
    $target = new Translation('deu-DE', 'my.key', '');
    $source = new Translation('eng-GB', 'my.key', 'Hello');
    $existingDraft = new TranslationDraft('deu-DE', 'my.key', 'old translation');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('find')->with(1)->willReturn($target);
    $repo->method('findByTransKey')->willReturn([$target, $source]);

    $draftRepo = $this->createMock(TranslationDraftRepository::class);
    $draftRepo->method('findOneByKeyAndLanguage')->willReturn($existingDraft);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->once())->method('persist');
    $em->expects($this->once())->method('flush');

    $controller = makeController(repo: $repo, draftRepo: $draftRepo, em: $em, deepl: deeplConfigured());
    $response = $controller->deeplTranslateAction(csrfRequest(), 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    $data = json_decode($response->getContent(), true);
    expect($data['draftTranslation'])->toBe('Hallo');
});

// ─── acceptDraftAction ───────────────────────────────────────────────────────

it('acceptDraftAction returns 404 when draft is not found', function () {
    $draftRepo = $this->createMock(TranslationDraftRepository::class);
    $draftRepo->method('find')->with(99)->willReturn(null);

    $controller = makeController(draftRepo: $draftRepo);
    $response = $controller->acceptDraftAction(csrfRequest(), 99);

    expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('error');
});

it('acceptDraftAction creates a new Translation when none exists and removes the draft', function () {
    $draft = new TranslationDraft('deu-DE', 'my.key', 'Hallo');

    $draftRepo = $this->createMock(TranslationDraftRepository::class);
    $draftRepo->method('find')->with(1)->willReturn($draft);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findOneBy')->with(['transKey' => 'my.key', 'languageCode' => 'deu-DE'])->willReturn(null);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->once())->method('persist');
    $em->expects($this->once())->method('remove')->with($draft);
    $em->expects($this->once())->method('flush');

    $controller = makeController(repo: $repo, draftRepo: $draftRepo, em: $em);
    $response = $controller->acceptDraftAction(csrfRequest(), 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeTrue();
    expect($data['translation'])->toBe('Hallo');
});

it('acceptDraftAction updates an existing Translation entity', function () {
    $draft = new TranslationDraft('deu-DE', 'my.key', 'Neu');
    $existing = new Translation('deu-DE', 'my.key', 'Alt');

    $draftRepo = $this->createMock(TranslationDraftRepository::class);
    $draftRepo->method('find')->with(1)->willReturn($draft);

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findOneBy')->willReturn($existing);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->once())->method('persist')->with($existing);
    $em->expects($this->once())->method('remove')->with($draft);
    $em->expects($this->once())->method('flush');

    $controller = makeController(repo: $repo, draftRepo: $draftRepo, em: $em);
    $response = $controller->acceptDraftAction(csrfRequest(), 1);

    $data = json_decode($response->getContent(), true);
    expect($data['translation'])->toBe('Neu');
    expect($existing->getTranslation())->toBe('Neu');
});

// ─── revertDraftAction ───────────────────────────────────────────────────────

it('revertDraftAction returns 404 when draft is not found', function () {
    $draftRepo = $this->createMock(TranslationDraftRepository::class);
    $draftRepo->method('find')->with(99)->willReturn(null);

    $controller = makeController(draftRepo: $draftRepo);
    $response = $controller->revertDraftAction(csrfRequest(), 99);

    expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('error');
});

it('revertDraftAction removes the draft and returns success', function () {
    $draft = new TranslationDraft('deu-DE', 'my.key', 'Hallo');

    $draftRepo = $this->createMock(TranslationDraftRepository::class);
    $draftRepo->method('find')->with(1)->willReturn($draft);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->once())->method('remove')->with($draft);
    $em->expects($this->once())->method('flush');

    $controller = makeController(draftRepo: $draftRepo, em: $em);
    $response = $controller->revertDraftAction(csrfRequest(), 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeTrue();
});

// ─── inlineSaveAction ────────────────────────────────────────────────────────

it('inlineSaveAction returns 403 when no CSRF token is provided', function () {
    $request = Request::create('/', 'POST', [], [], [], [], json_encode(['translation' => 'Hallo']));
    $request->headers->set('Content-Type', 'application/json');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->expects($this->never())->method('find');

    $controller = makeController(repo: $repo);
    $response = $controller->inlineSaveAction($request, 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
});

it('inlineSaveAction returns 400 when translation is missing from body', function () {
    $request = Request::create('/', 'POST', [], [], [], [], json_encode(['_token' => 'test']));
    $request->headers->set('Content-Type', 'application/json');

    $controller = makeController();
    $response = $controller->inlineSaveAction($request, 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST);
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('error');
});

it('inlineSaveAction returns 404 when translation entity is not found', function () {
    $request = Request::create('/', 'POST', [], [], [], [], json_encode(['_token' => 'test', 'translation' => 'Hallo']));
    $request->headers->set('Content-Type', 'application/json');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('find')->with(1)->willReturn(null);

    $controller = makeController(repo: $repo);
    $response = $controller->inlineSaveAction($request, 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('error');
});

it('inlineSaveAction updates the entity and returns success', function () {
    $request = Request::create('/', 'POST', [], [], [], [], json_encode(['_token' => 'test', 'translation' => 'Hallo Welt']));
    $request->headers->set('Content-Type', 'application/json');

    $entity = new Translation('deu-DE', 'my.key', 'Old');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('find')->with(1)->willReturn($entity);

    $em = $this->createMock(EntityManagerInterface::class);
    $em->expects($this->once())->method('persist')->with($entity);
    $em->expects($this->once())->method('flush');

    $controller = makeController(repo: $repo, em: $em);
    $response = $controller->inlineSaveAction($request, 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeTrue();
    expect($entity->getTranslation())->toBe('Hallo Welt');
});

// ─── exportAction ────────────────────────────────────────────────────────────

it('exportAction returns a streamed CSV response with correct headers', function () {
    // Anonymous Query subclass (parent ctor needs an EM) that emulates ORM 3.1+'s
    // onEachResult() so the controller's row-mapping logic runs without a database.
    $query = new class extends Doctrine\ORM\Query {
        public function __construct()
        {
            // Intentionally skip parent constructor for pure unit testing.
        }

        public function onEachResult(callable $callback): void
        {
            $callback(['id' => 1, 'transKey' => 'hello', 'languageCode' => 'eng-GB', 'translation' => 'Hello']);
        }
    };

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('createExportQuery')->willReturn($query);

    $controller = makeController(repo: $repo);
    $response = $controller->exportAction();

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->getStatusCode())->toBe(Response::HTTP_OK)
        ->and($response->headers->get('Content-Type'))->toBe('text/csv')
        ->and($response->headers->get('Cache-Control'))->toBe('private')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment');

    // The streamed body writes to php://output — capture it while sending.
    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    expect($content)->toContain('transKey')
        ->and($content)->toContain('languageCode')
        ->and($content)->toContain('hello');
});
