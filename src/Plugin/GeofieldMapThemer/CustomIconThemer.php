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
 *   name = @Translation("Custom Icon"),
 *   defaultSettings = {
 *    "icon_image_path" = NULL
 *   },
 * )
 */
class CustomIconThemer extends MapThemerBase {

  /**
   * {@inheritdoc}
   */
  public function buildMapThemerForm(array &$form, FormStateInterface $form_state) {
    $form['map_themer']['icon_image_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon Image'),
      '#size' => '120',
      '#description' => $this->t('Input the Specific Icon Image path (absolute path, or relative to the Drupal site root). If not set, or not found/loadable, the Default Google Marker will be used.'),
      '#default_value' => $this->defaultSettings('icon_image_path'),
      '#placeholder' => 'modules/custom/geofield_map/images/beachflag.png',
      '#element_validate' => [['Drupal\geofield_map\GeofieldMapFormElementsValidationTrait', 'urlValidate']],
    ];
  }

}
