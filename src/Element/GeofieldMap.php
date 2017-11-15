<?php

namespace Drupal\geofield_map\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield\Element\GeofieldElementBase;

/**
 * Provides a Geofield Map form element.
 *
 * @FormElement("geofield_map")
 */
class GeofieldMap extends GeofieldElementBase {

  /**
   * {@inheritdoc}
   */
  public static $components = array(
    'lat' => array(
      'title' => 'Latitude',
      'range' => 90,
    ),
    'lon' => array(
      'title' => 'Longitude',
      'range' => 180,
    ),
  );

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return array(
      '#input' => TRUE,
      '#process' => array(
        array($class, 'latLonProcess'),
      ),
      '#element_validate' => array(
        array($class, 'elementValidate'),
      ),
      '#theme_wrappers' => array('fieldset'),
    );
  }

  /**
   * Generates the Geofield Map form element.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   element. Note that $element must be taken by reference here, so processed
   *   child elements are taken over into $form_state.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function latLonProcess(array &$element, FormStateInterface $form_state, array &$complete_form) {

    if ($element['#map_library'] == 'leaflet') {
      $element['#attached']['library'][] = \Drupal::moduleHandler()->moduleExists('leaflet') ? 'leaflet/leaflet' : 'geofield_map/leaflet';
    }

    $mapid = 'map-' . $element['#id'];

    $element['map'] = [
      '#type' => 'fieldset',
      '#weight' => 0,
    ];

    if (strlen($element['#gmap_api_key']) > 0) {
      $element['map']['geocode'] = array(
        '#prefix' => '<label>' . t("Geocode address") . '</label>',
        '#type' => 'textfield',
        '#description' => t("Use this to geocode you search location"),
        '#size' => 60,
        '#maxlength' => 128,
        '#attributes' => [
          'id' => 'search-' . $element['#id'],
          'class' => ['form-text', 'form-autocomplete', 'geofield-map-search'],
        ],
      );
    }
    else {
      $element['map']['geocode_missing'] = array(
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => t('Gmap Api Key missing | The Geocode Address and ReverseGeocode functionality not available.'),
        '#attributes' => [
          'class' => ['geofield-map-apikey-missing'],
        ],
      );
    }

    $element['map']['geofield_map'] = array(
      '#theme' => 'geofield_google_map',
      '#mapid' => $mapid,
      '#width' => isset($element['#map_dimensions']['width']) ? $element['#map_dimensions']['width'] : '100%',
      '#height' => isset($element['#map_dimensions']['height']) ? $element['#map_dimensions']['height'] : '450px',
    );

    $element['map']['actions'] = [
      '#type' => 'actions',
    ];

    if (!empty($element['#click_to_find_marker']) && $element['#click_to_find_marker'] == TRUE) {
      $element['map']['actions']['click_to_find_marker'] = array(
        '#type' => 'button',
        '#value' => t('Find marker'),
        '#name' => 'geofield-map-center',
        '#attributes' => [
          'id' => $element['#id'] . '-click-to-find-marker',
        ],
      );
      $element['#attributes']['class'] = ['geofield-map-center'];
    }

    if (!empty($element['#click_to_place_marker']) && $element['#click_to_place_marker'] == TRUE) {
      $element['map']['actions']['click_to_place_marker'] = array(
        '#type' => 'button',
        '#value' => t('Place marker here'),
        '#name' => 'geofield-map-marker',
        '#attributes' => [
          'id' => $element['#id'] . '-click-to-place-marker',
        ],
      );
      $element['#attributes']['class'] = ['geofield-map-marker'];
    }

    if (!empty($element['#geolocation']) && $element['#geolocation'] == TRUE) {
      $element['#attached']['library'][] = 'geofield_map/geolocation';
      $element['map']['actions']['geolocation'] = array(
        '#type' => 'button',
        '#value' => t('Find my location'),
        '#name' => 'geofield-html5-geocode-button',
        '#attributes' => ['mapid' => $mapid],
      );
      $element['#attributes']['class'] = ['auto-geocode'];
    }

    static::elementProcess($element, $form_state, $complete_form);

    $element['lat']['#attributes']['id'] = 'lat-' . $element['#id'];
    $element['lon']['#attributes']['id'] = 'lon-' . $element['#id'];

    // Geoaddress Field Settings.
    if (!empty($element['#geoaddress_field']['field'])) {
      $complete_form[$element['#geoaddress_field']['field']]['widget'][0]['value']['#description'] = (string) t('This value will be synchronized with the Geofield Map Reverse-Geocoded value.');
      if ($element['#geoaddress_field']['hidden']) {
        $complete_form[$element['#geoaddress_field']['field']]['#attributes']['class'][] = 'geofield_map_geoaddress_field_hidden';
      }
      if ($element['#geoaddress_field']['disabled']) {
        $complete_form[$element['#geoaddress_field']['field']]['widget'][0]['value']['#attributes']['readonly'] = 'readonly';
        $complete_form[$element['#geoaddress_field']['field']]['widget'][0]['value']['#description'] = (string) t('This field is readonly. It will be synchronized with the Geofield Map Reverse-Geocoded value.');
      }
      // Ensure the geoaddress_field has got an #id, otherwise generate it.
      if (!isset($complete_form[$element['#geoaddress_field']['field']]['widget'][0]['value']['#id'])) {
        $complete_form[$element['#geoaddress_field']['field']]['widget'][0]['value']['#id'] = $element['#geoaddress_field']['field'] . '-0';
      }
    }

    // Attach Geofield Map Library.
    $element['#attached']['library'][] = 'geofield_map/geofield_map_widget';

    // The Node Form.
    /* @var \Drupal\Core\Entity\ContentEntityFormInterface $entityForm */
    $entityForm = $form_state->getBuildInfo()['callback_object'];
    $entity_operation = method_exists($entityForm, 'getOperation') ? $entityForm->getOperation() : 'any';

    $settings = [
      $mapid => [
        'entity_operation' => $entity_operation,
        'id' => $element['#id'],
        'gmap_api_key' => $element['#gmap_api_key'] && strlen($element['#gmap_api_key']) > 0 ? $element['#gmap_api_key'] : NULL,
        'name' => $element['#name'],
        'lat' => floatval($element['lat']['#default_value']),
        'lng' => floatval($element['lon']['#default_value']),
        'zoom_start' => intval($element['#zoom']['start']),
        'zoom_focus' => intval($element['#zoom']['focus']),
        'zoom_min' => intval($element['#zoom']['min']),
        'zoom_max' => intval($element['#zoom']['max']),
        'latid' => $element['lat']['#attributes']['id'],
        'lngid' => $element['lon']['#attributes']['id'],
        'searchid' => isset($element['map']['geocode']) ? $element['map']['geocode']['#attributes']['id'] : NULL,
        'geoaddress_field' => !empty($element['#geoaddress_field']['field']) ? $element['#geoaddress_field']['field'] : NULL,
        'geoaddress_field_id' => !empty($element['#geoaddress_field']['field']) ? $complete_form[$element['#geoaddress_field']['field']]['widget'][0]['value']['#id'] : NULL,
        'mapid' => $mapid,
        'widget' => TRUE,
        'map_library' => $element['#map_library'],
        'map_type' => $element['#map_type'],
        'map_type_selector' => $element['#map_type_selector'] ? TRUE : FALSE,
        'map_types_google' => $element['#map_types_google'],
        'map_types_leaflet' => $element['#map_types_leaflet'],
        'click_to_find_marker_id' => $element['#click_to_find_marker'] ? $element['map']['actions']['click_to_find_marker']['#attributes']['id'] : NULL,
        'click_to_find_marker' => $element['#click_to_find_marker'] ? TRUE : FALSE,
        'click_to_place_marker_id' => $element['#click_to_place_marker'] ? $element['map']['actions']['click_to_place_marker']['#attributes']['id'] : NULL,
        'click_to_place_marker' => $element['#click_to_place_marker'] ? TRUE : FALSE,
      ],
    ];

    $element['#attached']['drupalSettings'] = [
      'geofield_map' => $settings,
    ];
    return $element;
  }

}
