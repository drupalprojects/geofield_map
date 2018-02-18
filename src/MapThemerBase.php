<?php

namespace Drupal\geofield_map;

use Drupal\views\Plugin\views\PluginBase;

/**
 * A base class for MapThemer plugins.
 */
abstract class MapThemerBase extends PluginBase implements MapThemerInterface {

  /**
   * Get the defaultSettings for the Map Themer Plugin.
   *
   * @param string $k
   *   A specific defaultSettings key index.
   *
   * @return array|string
   *   The defaultSettings to be returned.
   */
  public function defaultSettings($k = NULL) {
    $default_settings = $this->pluginDefinition['defaultSettings'];
    if (!empty($key)) {
      $default_settings = $default_settings[$k];
    }
    return $default_settings;
  }

}
