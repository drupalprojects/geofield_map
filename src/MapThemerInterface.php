<?php

namespace Drupal\geofield_map;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;

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
   * Provides a Map Themer Color Table.
   *
   * @param array $form
   *   The form to be integrated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function buildMapThemerForm(array &$form, FormStateInterface $form_state);

}
