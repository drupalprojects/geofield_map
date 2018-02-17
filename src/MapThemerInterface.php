<?php

namespace Drupal\geofield_map;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an interface for Geofield Map Themers plugins.
 *
 * Geofield Map Themers are plugins that allow to differentiate map elements
 * (markers, poly-lines, polygons) based on specific dynamic logics .
 */
interface MapThemerInterface {

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
