<?php

namespace Drupal\jsonapi_menu\JsonApiResource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\system\MenuInterface;

class MenuResourceObject extends ResourceObject {
  public function __construct(MenuInterface $entity, ResourceType $resource_type, $menuItems) {
    $cacheability = CacheableMetadata::createFromObject($entity);
    $this->addMenuItemsCacheTags($cacheability, $menuItems);

    $fields = static::extractFieldsFromEntity($resource_type, $entity);
    $fields['items'] = $menuItems;

    parent::__construct(
      $cacheability,
      $resource_type,
      $entity->uuid(),
      NULL,
      $fields,
      new LinkCollection([]),
      $entity->language()
    );
  }

  /**
   * Add menu items cache tags.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   * @param array $items
   *
   * @return void
   */
  private function addMenuItemsCacheTags(CacheableMetadata &$cacheability, array $items): void {
    foreach ($items as $item) {
      if (!isset($item['meta']['entity_id'])) {
        continue;
      }
      $cacheability->addCacheTags(['menu_link_content:' . $item['meta']['entity_id']]);
      if (isset($item['below']) && is_array($item['below'])) {
        $this->addMenuItemsCacheTags($cacheability, $item['below']);
      }
    }
  }
}
