<?php

namespace Drupal\geofield_map;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\geofield_map\Annotation\MapThemer;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Provides a plugin manager for Geofield Map Themers.
 */
class MapThemerPluginManager extends DefaultPluginManager {

  use StringTranslationTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    ConfigFactoryInterface $config_factory,
    TranslationInterface $string_translation
  ) {
    parent::__construct('Plugin/GeofieldMap/Themer', $namespaces, $module_handler, MapThemerInterface::class, MapThemer::class);
    $this->alterInfo('geofield_map_themer_info');
    $this->setCacheBackend($cache_backend, 'geofield_map_themer_plugins');
  }

  /**
   * Return the array of plugins and their settings if any.
   *
   * @return array
   *   A list of plugins with their settings.
   */
  public function getPlugins() {
    $plugins_arguments = $this->config->get('plugins_options');

    // Convert old JSON config.
    // @TODO: This should be removed before the stable release 8.x-2.0.
    if (is_string($plugins_arguments) && $json = Json::decode($plugins_arguments)) {
      // Convert each plugins property in lowercase.
      $plugins_arguments = array_map(function ($old_plugin_arguments) {
        return array_combine(
          array_map(function ($k) {
            return strtolower($k);
          }, array_keys($old_plugin_arguments)),
          $old_plugin_arguments
        );
      }, $json);
    }

    $plugins_arguments = (array) $plugins_arguments;

    $definitions = array_map(function (array $definition) use ($plugins_arguments) {
      $plugins_arguments += [$definition['id'] => []];
      $definition += ['name' => $definition['id'], 'arguments' => []];
      $definition['arguments'] = array_merge((array) $definition['arguments'], (array) $plugins_arguments[$definition['id']]);

      return $definition;
    }, $this->getDefinitions());

    ksort($definitions);

    return $definitions;
  }

}
