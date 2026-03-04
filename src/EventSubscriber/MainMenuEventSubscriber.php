<?php

declare(strict_types=1);

namespace fork\IbexaThemeTranslationsBundle\EventSubscriber;

use Ibexa\AdminUi\Menu\Event\ConfigureMenuEvent;
use Ibexa\AdminUi\Menu\MainMenuBuilder;
use Ibexa\AdminUi\Menu\MenuItemFactory;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class MainMenuEventSubscriber implements EventSubscriberInterface
{
    private const ITEM_ADMIN__TRANSLATIONS = 'main__admin__translations';

    private MenuItemFactory $menuItemFactory;
    private PermissionResolver $permissionResolver;

    public function __construct(
        MenuItemFactory $menuItemFactory,
        PermissionResolver $permissionResolver
    ) {
        $this->menuItemFactory = $menuItemFactory;
        $this->permissionResolver = $permissionResolver;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMenuEvent::MAIN_MENU => ['onMainMenuConfigure', 0],
        ];
    }

    public function onMainMenuConfigure(ConfigureMenuEvent $event): void
    {
        $menu = $event->getMenu();

        if (!$this->permissionResolver->hasAccess('setup', 'system_info')) {
            return;
        }

        $menu->getChild(MainMenuBuilder::ITEM_ADMIN)->addChild(
            $this->menuItemFactory->createItem(
                self::ITEM_ADMIN__TRANSLATIONS,
                [
                    'label' => 'Theme Translations',
                    'route' => 'ibexa_theme_translations.list',
                    'attributes' => [
                        'class' => 'custom-menu-item',
                    ],
                    'linkAttributes' => [
                        'class' => 'custom-menu-item-link',
                    ],
                ]
            )
        );
    }
}
