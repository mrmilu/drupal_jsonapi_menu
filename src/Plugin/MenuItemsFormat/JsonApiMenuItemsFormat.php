<?php

namespace Drupal\jsonapi_menu\Plugin\MenuItemsFormat;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi_menu\Plugin\MenuItemsFormatBase;
use Drupal\system\MenuInterface;

/**
 * Plugin implementation of the 'json_api' format.
 *
 * @MenuItemsFormat(
 *   id = "json_api"
 * )
 */
class JsonApiMenuItemsFormat extends MenuItemsFormatBase {
  /**
   * Drupal\Core\Menu\MenuLinkTreeInterface definition.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuLinkTree;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->menuLinkTree = \Drupal::service('menu.link_tree');
    $this->moduleHandler = \Drupal::service('module_handler');
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->serializer = \Drupal::service('jsonapi.serializer');
    $this->resourceTypeRepository = \Drupal::service('jsonapi.resource_type.repository');
  }


  protected function getMenuItemResourceType(MenuLinkInterface $menuLink) {
    if ($this->moduleHandler->moduleExists('menu_item_extras')) {
      return $this->resourceTypeRepository->get('menu_link_content', $menuLink->getMenuName());
    }

    return $this->resourceTypeRepository->get('menu_link_content', 'menu_link_content');
  }

  /**
   * Returns an array of menu items.
   *
   * @param \Drupal\system\MenuInterface $menu
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   * @return array
   *   Menu items formatted in a specific format.
   */
  public function format(MenuInterface $menu, CacheableMetadata $cache) {
    $cache->addCacheableDependency($menu);

    $tree = $this->menuLinkTree->load($menu->id(), new MenuTreeParameters());
    if (!$tree) {
      return [];
    }
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkNodeAccess'],
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];

    $tree = $this->menuLinkTree->transform($tree, $manipulators);
    return $this->build($tree, $cache);
  }

  protected function build($tree, CacheableMetadata $cache) {
    $menuItems = [];
    $this->buildRecursive($tree, $menuItems, $cache);
    $data = new ResourceObjectData($menuItems, -1);
    $normalization = $this->serializer->normalize($data, 'api_json', []);
    $cache->addCacheableDependency($normalization);
    return $normalization->getNormalization();
  }

  protected function buildRecursive($tree, array &$items, CacheableMetadata $cache) {
    foreach ($tree as $menuTreeLink) {
      $menuLink = $menuTreeLink->link;
      $resourceType = $this->getMenuItemResourceType($menuLink);

      $id = $menuLink->getPluginId();
      [$plugin, $menuLinkEntityId] = explode(':', $id);

      $query = $this->entityTypeManager
        ->getStorage('menu_link_content')
        ->loadByProperties(['uuid' => $menuLinkEntityId])
      ;
      $menuLinkEntity = reset($query);
      $items[] = ResourceObject::createFromEntity($resourceType, $menuLinkEntity);

      if ($menuTreeLink->subtree) {
        $this->buildRecursive($menuTreeLink->subtree, $items, $cache);
      }
    }
  }
}
