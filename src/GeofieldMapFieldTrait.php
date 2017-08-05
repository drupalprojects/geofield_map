<?php

namespace Drupal\geofield_map;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class GeofieldMapFieldTrait.
 *
 * Provide common functions for Geofield Map fields.
 *
 * @package Drupal\geofield_map
 */
trait GeofieldMapFieldTrait {

  /**
   * Get the GMap Api Key from the geofield_map settings/configuration.
   *
   * @return string
   *   The GMap Api Key
   */
  private function getGmapApiKey() {
    /* @var \Drupal\Core\Config\ConfigFactoryInterface $config */
    $config = $this->config;
    $geofield_map_settings = $config->getEditable('geofield_map.settings');
    $gmap_api_key = $geofield_map_settings->get('gmap_api_key');

    // In the first release of Geofield_Map the google_api_key was stored in
    // the specific Field Widget settings.
    // So we try and copy into the geofield_map.settings config, in the case.
    if (!empty($gmap_api_key) && !empty($this->getSetting('map_google_api_key'))) {
      $gmap_api_key = $this->getSetting('map_google_api_key');
      $geofield_map_settings->set('gmap_api_key', $gmap_api_key)->save();
    }
    return $gmap_api_key;
  }

  /**
   * Form element validation handler for a Map Zoom level.
   */
  public static function zoomLevelValidate($element, FormStateInterface &$form_state) {
    // Get to the actual values in a form tree.
    $parents = $element['#parents'];
    $values = $form_state->getValues();
    for ($i = 0; $i < count($parents) - 1; $i++) {
      $values = $values[$parents[$i]];
    }
    // Check the initial map zoom level.
    $zoom = $element['#value'];
    $min_zoom = $values['min'];
    $max_zoom = $values['max'];
    if ($zoom < $min_zoom || $zoom > $max_zoom) {
      $form_state->setError($element, t('The @zoom_field should be between the Minimum and the Maximum Zoom levels.', ['@zoom_field' => $element['#title']]));
    }
  }

  /**
   * Form element validation handler for the Map Max Zoom level.
   */
  public static function maxZoomLevelValidate($element, FormStateInterface &$form_state) {
    // Get to the actual values in a form tree.
    $parents = $element['#parents'];
    $values = $form_state->getValues();
    for ($i = 0; $i < count($parents) - 1; $i++) {
      $values = $values[$parents[$i]];
    }
    // Check the max zoom level.
    $min_zoom = $values['min'];
    $max_zoom = $element['#value'];
    if ($max_zoom && $max_zoom <= $min_zoom) {
      $form_state->setError($element, t('The Max Zoom level should be above the Minimum Zoom level.'));
    }
  }

  /**
   * Form element json format validation handler.
   */
  public static function jsonValidate($element, FormStateInterface &$form_state) {
    // Check the jsonValue.
    if (!empty($element['#value']) && JSON::decode($element['#value']) == NULL) {
      $form_state->setError($element, t('The @field field is not valid Json Format.', ['@field' => $element['#title']]));
    }
  }

  /**
   * Form element url format validation handler.
   */
  public static function urlValidate($element, FormStateInterface &$form_state) {
    $path = $element['#value'];
    // Check the jsonValue.
    if (UrlHelper::isExternal($path) && !UrlHelper::isValid($path, TRUE)) {
      $form_state->setError($element, t('The @field field is not valid Url Format.', ['@field' => $element['#title']]));
    }
    elseif (!UrlHelper::isExternal($path)) {
      $path = Url::fromUri('base:' . $path, ['absolute' => TRUE])->toString();
      if (!UrlHelper::isValid($path)) {
        $form_state->setError($element, t('The @field field is not valid internal Drupal path.', ['@field' => $element['#title']]));
      }
    }
  }

}
