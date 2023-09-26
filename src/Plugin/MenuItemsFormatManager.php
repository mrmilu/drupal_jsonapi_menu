<?php

namespace Drupal\jsonapi_menu\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides an MenuItemsFormat plugin manager.
 *
 * @see \Drupal\jsonapi_menu\Annotation\MenuItemsFormat
 * @see \Drupal\jsonapi_menu\MenuItemsFormat\MenuItemsFormatInterface
 * @see plugin_api
 */
class MenuItemsFormatManager extends DefaultPluginManager {
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler
  ) {
    parent::__construct(
      'Plugin/MenuItemsFormat',
      $namespaces,
      $module_handler,
      'Drupal\jsonapi_menu\Plugin\\MenuItemsFormatInterface',
      'Drupal\jsonapi_menu\Annotation\MenuItemsFormat'
    );
    $this->alterInfo('jsonapi_menu_menu_items_format_info');
    $this->setCacheBackend($cache_backend, 'jsonapi_menu_menu_items_format_info_plugins');
  }
}
