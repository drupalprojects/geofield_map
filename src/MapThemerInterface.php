<?php

namespace Drupal\geofield_map;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield_map\Plugin\views\style\GeofieldGoogleMapViewStyle;

/**
 * Provides an interface for Geofield Map Themers plugins.
 *
 * Geofield Map Themers are plugins that allow to differentiate map elements
 * (markers, poly-lines, polygons) based on specific dynamic logics .
 */
interface MapThemerInterface extends PluginInspectionInterface {

  /**
   * Get the MapThemer name property.
   *
   * @return string
   *   The MapThemer name.
   */
  public function getName();

  /**
   * Get the defaultSettings for the Map Themer Plugin.
   *
   * @param string $k
   *   A specific defaultSettings key index.
   *
   * @return array|string
   *   The defaultSettings to be returned.
   */
  public function defaultSettings($k = NULL);

  /**
   * Provides a Map Themer Options Element.
   *
   * @param array $defaults
   *   The default values/settings.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\geofield_map\Plugin\views\style\GeofieldGoogleMapViewStyle $geofieldMapView
   *   The Geofield Map View dispaly object.
   *
   * @return array
   *   The Map Themer Options Element
   */
  public function buildMapThemerElement(array $defaults, FormStateInterface $form_state, GeofieldGoogleMapViewStyle $geofieldMapView);

  /**
   * Retrieve the icon for theming definition.
   *
   * @param array $datum
   *   The geometry feature array definition.
   * @param \Drupal\geofield_map\Plugin\views\style\GeofieldGoogleMapViewStyle $geofieldMapView
   *   The Geofield Map View dispaly object.
   * @param mixed $map_theming_values
   *   The Map themer mapping values.
   *
   * @return mixed
   *   The icon definition.
   */
  public function getIcon(array $datum, GeofieldGoogleMapViewStyle $geofieldMapView, $map_theming_values);

}
