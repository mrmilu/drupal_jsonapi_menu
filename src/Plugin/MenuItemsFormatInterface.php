<?php

namespace Drupal\jsonapi_menu\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\system\MenuInterface;

interface MenuItemsFormatInterface extends PluginInspectionInterface {
  /**
   * Returns an array of menu items.
   *
   * @param \Drupal\system\MenuInterface $menu
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   * @return array
   *   Menu items formatted in a specific format.
   */
  public function format(MenuInterface $menu, CacheableMetadata $cache);
}
