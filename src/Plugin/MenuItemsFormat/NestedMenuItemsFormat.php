<?php

namespace Drupal\jsonapi_menu\Plugin\MenuItemsFormat;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_menu\Plugin\MenuItemsFormatBase;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Plugin implementation of the 'nested' format.
 *
 * @MenuItemsFormat(
 *   id = "nested"
 * )
 */
class NestedMenuItemsFormat extends MenuItemsFormatBase implements ContainerFactoryPluginInterface {

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
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  static array $resourceTypeCache = [];

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
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MenuLinkTreeInterface $menuLinkTree, ModuleHandlerInterface $moduleHandler, EntityTypeManagerInterface $entityTypeManager, SerializerInterface $serializer, ResourceTypeRepositoryInterface $resourceTypeRepository, EntityRepositoryInterface $entityRepository, EntityFieldManagerInterface $entityFieldManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuLinkTree = $menuLinkTree;
    $this->moduleHandler = $moduleHandler;
    $this->entityTypeManager = $entityTypeManager;
    $this->serializer = $serializer;
    $this->resourceTypeRepository = $resourceTypeRepository;
    $this->entityRepository = $entityRepository;
    $this->entityFieldManager = $entityFieldManager;
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
      $container->get('entity_field.manager'),
    );
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
    foreach ($tree as $menuTreeElement) {
      $menuItems[] = $this->buildMenuItem($menuTreeElement, $cache);
    }
    return $menuItems;
  }

  protected function getResourceType(string $menuName) {
    if (isset(self::$resourceTypeCache[$menuName])) {
      return self::$resourceTypeCache[$menuName];
    }

    self::$resourceTypeCache[$menuName] = $this->resourceTypeRepository->get('menu_link_content', $menuName);
    return self::$resourceTypeCache[$menuName];
  }

  protected function getMenuItemData(MenuLinkTreeElement $treeElement, CacheableMetadata $cache) {
    $menuLink = $treeElement->link;

    $url = $menuLink->getUrlObject()->toString(TRUE);
    assert($url instanceof GeneratedUrl);
    $cache->addCacheableDependency($url);

    $id = $menuLink->getPluginId();
    $plugin = '';
    if (strpos($id, ':') !== FALSE) {
      [$plugin, $menuLinkEntityId] = explode(':', $id);
    }

    $data = [
      'id' => $id,
      'description' => $menuLink->getDescription(),
      'enabled' => $menuLink->isEnabled(),
      'expanded' => $menuLink->isExpanded(),
      'menu_name' => $menuLink->getMenuName(),
      'meta' => $menuLink->getMetaData(),
      'options' => $menuLink->getOptions(),
      'parent' => $menuLink->getParent(),
      'provider' => $menuLink->getProvider(),
      'route' => [
        'name' => $menuLink->getRouteName(),
        'parameters' => $menuLink->getRouteParameters(),
      ],
      'title' => (string) $menuLink->getTitle(),
      'url' => $url->getGeneratedUrl(),
      'weight' => (int) $menuLink->getWeight(),
      'uri' => NULL,
    ];

    if ($plugin === 'menu_link_content') {
      /* @var $menuLinkContentEntity MenuLinkContentInterface */
      $menuLinkContentEntity = $this->entityRepository->loadEntityByUuid('menu_link_content', $menuLinkEntityId);
      $menuLinkContentEntity = $this->entityRepository->getTranslationFromContext($menuLinkContentEntity);

      $this->addMenuLinkContentFieldValues($menuLink, $menuLinkContentEntity, $data);
      $data['uri'] = $menuLinkContentEntity->link->uri;
    }

    return $data;
  }

  protected function buildMenuItem(MenuLinkTreeElement $treeElement, CacheableMetadata $cache) {
    $item = $this->getMenuItemData($treeElement, $cache);
    $item['below'] = [];

    $menuLinkCache = new CacheableMetadata();
    $menuLinkCache->addCacheableDependency($treeElement->access);
    $menuLinkCache->addCacheableDependency($cache);

    if ($treeElement->subtree) {
      $item['below'] = $this->build($treeElement->subtree, $cache);
    }

    return $item;
  }

  /**
   * Get menu fields.
   *
   * @param string $id
   *   Menu id.
   *
   * @return string[]
   *   Return fieldNames.
   */
  public function getMenuFields($id) {
    $fieldNames = [];
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions('menu_link_content', $id);
    $keys = array_keys($fieldDefinitions);
    foreach ($keys as $fieldName) {
      if ($fieldDefinitions[$fieldName] instanceof FieldConfigInterface) {
        $fieldNames[] = $fieldName;
      }
    }
    return $fieldNames;
  }

  protected function addMenuLinkContentFieldValues(MenuLinkInterface $menuLink, MenuLinkContentInterface $menuLinkContentEntity, &$data) {
    if ($this->moduleHandler->moduleExists('menu_item_extras')) {
      $resourceType = $this->resourceTypeRepository->get('menu_link_content', $menuLink->getMenuName());
      $resourceObject = ResourceObject::createFromEntity($resourceType, $menuLinkContentEntity);
      $fields = $this->getMenuFields($menuLink->getMenuName());
      foreach ($fields as $key) {
        $field = $menuLinkContentEntity->get($key);
        $normalization = $this->serializer->normalize($field, 'api_json', ['resource_object' => $resourceObject]);
        $data[$key] = $normalization->getNormalization();
        /** @var \Drupal\Core\Entity\EntityInterface $entity */
        if ($entity = $field->entity) {
          $data[$key]['type'] = $entity->getEntityTypeId() . '--' . $entity->bundle();
          $data[$key]['id'] = $entity->uuid();
        }
      }
    }
  }
}
