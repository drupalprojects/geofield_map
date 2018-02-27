<?php

namespace Drupal\geofield_map\Plugin\GeofieldMapThemer;

use Drupal\geofield_map\MapThemerBase;
use Drupal\Core\Form\FormStateInterface;

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
 *   defaultSettings = {
 *    "custom_icon" = NULL
 *   },
 * )
 */
class CustomIconThemer extends MapThemerBase {

  /**
   * {@inheritdoc}
   */
  public function buildMapThemerElement(array $defaults, FormStateInterface $form_state) {

    $user_input = $form_state->getUserInput();
    $input_element = $user_input['style_options']['map_marker_and_infowindow']['theming']['element'];

    $default_value = !empty($defaults['map_marker_and_infowindow']['theming']['element']) ? $defaults['map_marker_and_infowindow']['theming']['element'] : $this->defaultSettings('custom_icon');

    $default_element = !empty($input_element) ? $input_element : $default_value;
    $element = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Icon Image'),
      '#size' => '120',
      '#description' => $this->t('Input the Specific Icon Image path (absolute path, or relative to the Drupal site root). If not set, or not found/loadable, the Default Google Marker will be used.'),
      '#default_value' => $default_element,
      '#placeholder' => 'modules/custom/geofield_map/images/beachflag.png',
      '#element_validate' => [['Drupal\geofield_map\GeofieldMapFormElementsValidationTrait', 'urlValidate']],
    ];

    return $element;

  }

}