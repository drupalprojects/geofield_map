<?php

namespace Drupal\geofield_map;

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
   */
  public function mapThemerColorTableForm(array &$form);

}
