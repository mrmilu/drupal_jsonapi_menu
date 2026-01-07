<?php

namespace Drupal\jsonapi_menu\Plugin\MenuItemsFormat;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_menu\Plugin\MenuItemsFormatBase;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Plugin implementation of the 'json_api' format.
 *
 * @MenuItemsFormat(
 *   id = "json_api"
 * )
 */
class JsonApiMenuItemsFormat extends MenuItemsFormatBase implements ContainerFactoryPluginInterface {
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

  /**
   * The entity respository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuLinkTree
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resourceTypeRepository
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MenuLinkTreeInterface $menuLinkTree, ModuleHandlerInterface $moduleHandler, EntityTypeManagerInterface $entityTypeManager, SerializerInterface $serializer, ResourceTypeRepositoryInterface $resourceTypeRepository, EntityRepositoryInterface $entityRepository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuLinkTree = $menuLinkTree;
    $this->moduleHandler = $moduleHandler;
    $this->entityTypeManager = $entityTypeManager;
    $this->serializer = $serializer;
    $this->resourceTypeRepository = $resourceTypeRepository;
    $this->entityRepository = $entityRepository;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('jsonapi.serializer'),
      $container->get('jsonapi.resource_type.repository'),
      $container->get('entity.repository'),
    );
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

      if (!empty($menuLinkEntityId)) {
        $query = $this->entityTypeManager
          ->getStorage('menu_link_content')
          ->loadByProperties(['uuid' => $menuLinkEntityId]);
        $menuLinkEntity = reset($query);
        $menuLinkEntity = $this->entityRepository->getTranslationFromContext($menuLinkEntity);
        $items[] = ResourceObject::createFromEntity($resourceType, $menuLinkEntity);
      }
      else {
        $url = $menuLink->getUrlObject()->toString();

        $items[] = new ResourceObject(
          $cache,
          $resourceType,
          crypt($menuLink->getPluginId(), 'salt'),
          NULL,
          [
            'title' => $menuLink->getTitle(),
            'url' => $url,
            'weight' => $menuLink->getWeight(),
            'enabled' => $menuLink->isEnabled(),
          ],
          new \Drupal\jsonapi\JsonApiResource\LinkCollection([])
        );
      }

      if ($menuTreeLink->subtree) {
        $this->buildRecursive($menuTreeLink->subtree, $items, $cache);
      }
    }
  }
}
