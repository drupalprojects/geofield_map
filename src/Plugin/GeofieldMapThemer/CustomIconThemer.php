<?php

namespace Drupal\geofield_map\Plugin\GeofieldMapThemer;

use Drupal\geofield_map\MapThemerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield_map\Plugin\views\style\GeofieldGoogleMapViewStyle;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup geofield_map_themers_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @MapThemer(
 *   id = "custom_icon",
 *   name = @Translation("Custom Icon Image"),
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
  public function buildMapThemerElement(array $defaults, FormStateInterface $form_state, GeofieldGoogleMapViewStyle $geofieldMapView) {

    // Get the Default Themer Element.
    $default_element = $this->getDefaultThemerElement($defaults, $form_state);

    $element = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Icon Image'),
      '#size' => '120',
      '#description' => $this->t('Input the Specific Icon Image path (absolute path, or relative to the Drupal site root prefixed with a trailing hash). If not set, or not found/loadable, the Default Google Marker will be used.'),
      '#default_value' => $default_element,
      '#placeholder' => '/modules/custom/geofield_map/images/beachflag.png',
      '#element_validate' => [['Drupal\geofield_map\GeofieldMapFormElementsValidationTrait', 'urlValidate']],
    ];

    return $element;

  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(array $datum, GeofieldGoogleMapViewStyle $geofieldMapView, $map_theming_values) {
    // The Custom Icon Themer plugin defines a unique icon value.
    $icon_value = $map_theming_values;
    return $icon_value;
  }

}
