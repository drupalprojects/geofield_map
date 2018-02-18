<?php

namespace Drupal\geofield_map\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a base class for Geofield Map Themer plugin annotations.
 *
 * @Annotation
 */
class MapThemer extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the Geofield Map Themer plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $name;

  /**
   * Settings for the Themer.
   *
   * @var array
   */
  public $settings = [];

}
