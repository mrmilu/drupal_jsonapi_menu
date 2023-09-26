<?php

namespace Drupal\jsonapi_menu\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a MenuItemsFormat annotation object.
 * @Annotation
 */
class MenuItemsFormat extends Plugin {
  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;
}
