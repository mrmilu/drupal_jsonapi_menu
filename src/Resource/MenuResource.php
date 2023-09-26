<?php

namespace Drupal\jsonapi_menu\Resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi_menu\Plugin\MenuItemsFormatManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\LinkCollection;
use Drupal\jsonapi\JsonApiResource\NullIncludedData;
use Drupal\jsonapi\JsonApiResource\ResourceObjectData;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi_menu\JsonApiResource\MenuResourceObject;
use Drupal\jsonapi_resources\Resource\ResourceBase;
use Drupal\system\MenuInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

final class MenuResource extends ResourceBase implements ContainerInjectionInterface {
  /**
   * The format manager.
   *
   * @var \Drupal\jsonapi_menu\Plugin\MenuItemsFormatManager
   */
  protected $menuItemsFormatManager;

  public function __construct(MenuItemsFormatManager $menuItemsFormatManager) {
    $this->menuItemsFormatManager = $menuItemsFormatManager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.jsonapi_menu.menu_items_format'),
    );
  }

  /**
   * Process the resource request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\system\MenuInterface $menu
   *   The menu.
   *
   * @return \Drupal\jsonapi\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function process(Request $request, MenuInterface $menu): ResourceResponse {
    $includes = new NullIncludedData();
    $meta = [];
    $links = new LinkCollection([]);

    $resourceObject = $this->getMenuResourceObject($menu);
    $data = new ResourceObjectData([$resourceObject], 1);
    $document = new JsonApiDocumentTopLevel($data, $includes, $links, $meta);
    return new ResourceResponse($document, 200, []);
  }

  protected function getMenuResourceObject(MenuInterface $menu) {
    $resourceType = $this->resourceTypeRepository->get($menu->getEntityTypeId(), $menu->getEntityTypeId());
    $menuItems = $this->getMenuItemsField($menu);
    return new MenuResourceObject($menu, $resourceType, $menuItems);
  }

  protected function getNotCache() {
    $cache = new CacheableMetadata();
    $cache->addCacheableDependency(NULL);
    return $cache;
  }

  protected function getMenuItemsFormat() {
    return \Drupal::config('jsonapi_menu.settings')->get('format');
  }

  protected function getMenuItemsField(MenuInterface $menu) {
    $cache = new CacheableMetadata();

    $format = $this->getMenuItemsFormat();
    /* @var $formatter \Drupal\jsonapi_menu\Plugin\MenuItemsFormatInterface */
    $formatter = $this->menuItemsFormatManager->createInstance($format);
    return $formatter->format($menu, $cache);
  }

  public function getRouteResourceTypes(Route $route, string $route_name): array {
    $resource_types = [];
    $resource_types[] = $this->resourceTypeRepository->get('menu', 'menu');
    return $resource_types;
  }
}
