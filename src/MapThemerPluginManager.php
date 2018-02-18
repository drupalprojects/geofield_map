<?php

namespace Drupal\geofield_map;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\geofield_map\Annotation\MapThemer;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides a plugin manager for Geofield Map Themers.
 */
class MapThemerPluginManager extends DefaultPluginManager {

  /**
   * Constructor of the a Geofield Map Themers plugin manager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations,.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/GeofieldMapThemer', $namespaces, $module_handler, MapThemerInterface::class, MapThemer::class);

    $this->alterInfo('geofield_map_themer_info');
    $this->setCacheBackend($cache_backend, 'geofield_map_themer_plugins');
  }

  /**
   * Generate an Options array for all the MapThemers plugins.
   *
   * @return mixed[]
   *   An array of MapThemers plugins Options. Keys are plugin IDs.
   */
  public function getThemersOptions() {
    $options = [];
    foreach ($this->getDefinitions() as $k => $map_themer) {
      /* @var \Drupal\Core\StringTranslation\TranslatableMarkup $map_themer['name'] */
      $options[$k] = $map_themer['name']->render();
    }
    return $options;
  }

}
