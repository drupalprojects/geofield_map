<?php

namespace Drupal\geofield_map\Plugin\GeofieldMapThemer;

use Drupal\geofield_map\MapThemerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield_map\Plugin\views\style\GeofieldGoogleMapViewStyle;
use Drupal\Core\Entity\EntityInterface;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup geofield_map_themers_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @MapThemer(
 *   id = "geofieldmap_custom_icon",
 *   name = @Translation("Geofield Map Custom Icon Image"),
 *   description = "This Geofield Map Themer allows the definition of a unique custom Marker Icon, valid for all the Map Markers.",
 *   type = "single_value",
 *   defaultSettings = {
 *    "values" = NULL
 *   },
 * )
 */
class CustomIconThemer extends MapThemerBase {

  /**
   * {@inheritdoc}
   */
  public function buildMapThemerElement(array $defaults, array &$form, FormStateInterface $form_state, GeofieldGoogleMapViewStyle $geofieldMapView) {

    // Get the existing (Default) Element settings.
    $default_element = $this->getDefaultThemerElement($defaults, $form_state);

    $fid = (integer) !empty($default_element['icon_file']['fids']) ? $default_element['icon_file']['fids'] : NULL;
    $element = [
      '#type' => 'container',
      'icon_file' => $this->getFileIconElement($fid[0]),
    ];

    return $element;

  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(array $datum, GeofieldGoogleMapViewStyle $geofieldMapView, EntityInterface $entity, $map_theming_values) {
    // The Custom Icon Themer plugin defines a unique icon value.
    $icon_value = $map_theming_values;
    return $icon_value;
  }

}
