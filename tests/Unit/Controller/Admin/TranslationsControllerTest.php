<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use Ibexa\AutomatedTranslation\ClientProvider;
use Ibexa\Contracts\AutomatedTranslation\Client\ClientInterface;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Core\MVC\Symfony\Locale\LocaleConverterInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use vardumper\IbexaThemeTranslationsBundle\Controller\Admin\TranslationsController;
use vardumper\IbexaThemeTranslationsBundle\Entity\Translation;
use vardumper\IbexaThemeTranslationsBundle\Entity\TranslationDraft;
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

function makeController(
    ?TranslationRepository $repo = null,
    ?TranslationDraftRepository $draftRepo = null,
    ?FormFactoryInterface $formFactory = null,
    ?EntityManagerInterface $em = null,
    ?LanguageResolverInterface $resolver = null,
    ?LanguageService $languageService = null,
    ?DeeplTranslationService $deepl = null,
): TranslationsController {
    return new TranslationsController(
        $repo ?? testMock(TranslationRepository::class),
        $draftRepo ?? testMock(TranslationDraftRepository::class),
        $formFactory ?? testMock(FormFactoryInterface::class),
        $em ?? testMock(EntityManagerInterface::class),
        $resolver ?? testMock(LanguageResolverInterface::class),
        $languageService ?? testMock(LanguageService::class),
        $deepl ?? deeplNotConfigured(),
    );
}

// ─── editAction ──────────────────────────────────────────────────────────────

it('editAction returns 404 when id is null', function () {
    $controller = makeController();
    $response = $controller->editAction(new Request(), null);

    expect($response->getStatusCode())->toBe(404);
    expect($response->getContent())->toContain('No id provided');
});

// ─── deleteAction ─────────────────────────────────────────────────────────────

it('deleteAction returns 404 when id is null', function () {
    $controller = makeController();
    $response = $controller->deleteAction(null);

    expect($response->getStatusCode())->toBe(404);
    expect($response->getContent())->toContain('No id provided');
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
    expect(fn () => $controller->deleteAction(1))->toThrow(Error::class);
});

// ─── deeplTranslateAction ────────────────────────────────────────────────────

it('deeplTranslateAction returns 503 when DeepL is not configured', function () {
    $controller = makeController(deepl: deeplNotConfigured());
    $response = $controller->deeplTranslateAction(new Request(), 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_SERVICE_UNAVAILABLE);
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('error');
});

it('deeplTranslateAction returns 404 when translation entity is not found', function () {
    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('find')->with(99)->willReturn(null);

    $controller = makeController(repo: $repo, deepl: deeplConfigured());
    $response = $controller->deeplTranslateAction(new Request(), 99);

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
    $response = $controller->deeplTranslateAction(new Request(), 1);

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
    $response = $controller->deeplTranslateAction(new Request(), 1);

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
    $response = $controller->deeplTranslateAction(new Request(), 1);

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
    $response = $controller->deeplTranslateAction(new Request(), 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    $data = json_decode($response->getContent(), true);
    expect($data['draftTranslation'])->toBe('Hallo');
});

// ─── acceptDraftAction ───────────────────────────────────────────────────────

it('acceptDraftAction returns 404 when draft is not found', function () {
    $draftRepo = $this->createMock(TranslationDraftRepository::class);
    $draftRepo->method('find')->with(99)->willReturn(null);

    $controller = makeController(draftRepo: $draftRepo);
    $response = $controller->acceptDraftAction(99);

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
    $response = $controller->acceptDraftAction(1);

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
    $response = $controller->acceptDraftAction(1);

    $data = json_decode($response->getContent(), true);
    expect($data['translation'])->toBe('Neu');
    expect($existing->getTranslation())->toBe('Neu');
});

// ─── revertDraftAction ───────────────────────────────────────────────────────

it('revertDraftAction returns 404 when draft is not found', function () {
    $draftRepo = $this->createMock(TranslationDraftRepository::class);
    $draftRepo->method('find')->with(99)->willReturn(null);

    $controller = makeController(draftRepo: $draftRepo);
    $response = $controller->revertDraftAction(99);

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
    $response = $controller->revertDraftAction(1);

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    $data = json_decode($response->getContent(), true);
    expect($data['success'])->toBeTrue();
});

// ─── inlineSaveAction ────────────────────────────────────────────────────────

it('inlineSaveAction returns 400 when translation is missing from body', function () {
    $request = Request::create('/', 'POST', [], [], [], [], json_encode([]));
    $request->headers->set('Content-Type', 'application/json');

    $controller = makeController();
    $response = $controller->inlineSaveAction($request, 1);

    expect($response->getStatusCode())->toBe(Response::HTTP_BAD_REQUEST);
    $data = json_decode($response->getContent(), true);
    expect($data)->toHaveKey('error');
});

it('inlineSaveAction returns 404 when translation entity is not found', function () {
    $request = Request::create('/', 'POST', [], [], [], [], json_encode(['translation' => 'Hallo']));
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
    $request = Request::create('/', 'POST', [], [], [], [], json_encode(['translation' => 'Hallo Welt']));
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

it('exportAction returns a CSV response with correct headers', function () {
    $t1 = new Translation('eng-GB', 'hello', 'Hello');
    $t2 = new Translation('deu-DE', 'hello', 'Hallo');

    $repo = $this->createMock(TranslationRepository::class);
    $repo->method('findAll')->willReturn([$t1, $t2]);

    $controller = makeController(repo: $repo);
    $response = $controller->exportAction();

    expect($response->getStatusCode())->toBe(Response::HTTP_OK);
    expect($response->headers->get('Content-type'))->toBe('text/csv');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->getContent())->toContain('transKey');
    expect($response->getContent())->toContain('languageCode');
});
