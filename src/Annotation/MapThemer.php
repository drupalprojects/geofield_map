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
   * The Geofield Map Themer plugin types.
   *
   * @var string
   *
   * Possible values might be the followings:
   * - "single_value"
   * - "key_value"
   * - "int_interval"
   * - "float_interval"
   */
  public $type;

  /**
   * Settings for the Themer.
   *
   * @var array
   */
  public $defaultSettings = [];

}
