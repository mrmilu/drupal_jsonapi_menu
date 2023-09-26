<?php

namespace Drupal\jsonapi_menu\JsonApiResource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\system\MenuInterface;

class MenuResourceObject extends ResourceObject {
  public function __construct(MenuInterface $entity, ResourceType $resource_type, $menuItems) {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency(NULL);

    $fields = static::extractFieldsFromEntity($resource_type, $entity);
    $fields['menu_items'] = $menuItems;

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
}
