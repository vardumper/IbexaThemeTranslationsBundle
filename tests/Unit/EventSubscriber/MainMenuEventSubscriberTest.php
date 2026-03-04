<?php

declare(strict_types=1);

use Ibexa\AdminUi\Menu\Event\ConfigureMenuEvent;
use Ibexa\AdminUi\Menu\MenuItemFactory;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use vardumper\IbexaThemeTranslationsBundle\EventSubscriber\MainMenuEventSubscriber;

uses(PHPUnit\Framework\TestCase::class);

it('subscribes to the MAIN_MENU configure event', function () {
    $events = MainMenuEventSubscriber::getSubscribedEvents();

    expect($events)->toHaveKey(ConfigureMenuEvent::MAIN_MENU);
});

it('adds the translations menu item when the user has setup/system_info access', function () {
    $menuItemFactory = $this->getMockBuilder(MenuItemFactory::class)
        ->disableOriginalConstructor()
        ->getMock();
    $permissionResolver = $this->createMock(PermissionResolver::class);
    $permissionResolver->method('hasAccess')->with('setup', 'system_info')->willReturn(true);

    $adminItem = $this->createMock(ItemInterface::class);
    $adminItem->expects($this->once())->method('addChild');

    $createdItem = $this->createMock(ItemInterface::class);
    $menuItemFactory->method('createItem')->willReturn($createdItem);

    $rootMenu = $this->createMock(ItemInterface::class);
    $rootMenu->method('getChild')->willReturn($adminItem);

    $factory = $this->createMock(FactoryInterface::class);
    $event = new ConfigureMenuEvent($factory, $rootMenu);

    $subscriber = new MainMenuEventSubscriber($menuItemFactory, $permissionResolver);
    $subscriber->onMainMenuConfigure($event);
});

it('does not add the menu item when the user lacks setup/system_info access', function () {
    $menuItemFactory = $this->getMockBuilder(MenuItemFactory::class)
        ->disableOriginalConstructor()
        ->getMock();
    $permissionResolver = $this->createMock(PermissionResolver::class);
    $permissionResolver->method('hasAccess')->with('setup', 'system_info')->willReturn(false);

    $rootMenu = $this->createMock(ItemInterface::class);
    $rootMenu->expects($this->never())->method('getChild');

    $factory = $this->createMock(FactoryInterface::class);
    $event = new ConfigureMenuEvent($factory, $rootMenu);

    $subscriber = new MainMenuEventSubscriber($menuItemFactory, $permissionResolver);
    $subscriber->onMainMenuConfigure($event);
});
