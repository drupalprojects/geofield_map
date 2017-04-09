<?php

namespace Drupal\geofield_map\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\geofield\Plugin\Field\FieldWidget\GeofieldLatLonWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Url;

/**
 * Plugin implementation of the 'geofield_map' widget.
 *
 * @FieldWidget(
 *   id = "geofield_map",
 *   label = @Translation("Geofield Map"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeofieldMapWidget extends GeofieldLatLonWidget implements ContainerFactoryPluginInterface {

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * The Renderer service.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The EntityField Manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Lat Lon widget components.
   *
   * @var array
   */
  public $components = ['lon', 'lat'];

  /**
   * Leaflet Map Tile Layers.
   *
   * Free Leaflet Tile Layers from here:
   * http://leaflet-extras.github.io/leaflet-providers/preview/index.html .
   *
   * @var array
   */
  protected $leafletTileLayers = [
    'OpenStreetMap_Mapnik' => [
      'label' => 'OpenStreetMap Mapnik',
      'url' => 'http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      'options' => [
        'maxZoom' => 19,
        'attribution' => '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      ],
    ],
    'OpenTopoMap' => [
      'label' => 'OpenTopoMap',
      'url' => 'http://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
      'options' => [
        'maxZoom' => 17,
        'attribution' => 'Map data: &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)',
      ],
    ],
    'OpenMapSurfer_Roads' => [
      'label' => 'OpenMapSurfer Roads',
      'url' => 'http://korona.geog.uni-heidelberg.de/tiles/roads/x={x}&y={y}&z={z}',
      'options' => [
        'maxZoom' => 20,
        'attribution' => 'Imagery from <a href="http://giscience.uni-hd.de/">GIScience Research Group @ University of Heidelberg</a> &mdash; Map data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      ],
    ],
    'Stamen_Toner' => [
      'label' => 'Stamen Toner',
      'url' => 'http://stamen-tiles-{s}.a.ssl.fastly.net/toner/{z}/{x}/{y}.{ext}',
      'options' => [
        'minZoom' => 0,
        'maxZoom' => 20,
        'ext' => 'png',
        'attribution' => 'Map tiles by <a href="http://stamen.com">Stamen Design</a>, <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a> &mdash; Map data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      ],
    ],
    'Stamen_Watercolor' => [
      'label' => 'Stamen Watercolor',
      'url' => 'http://stamen-tiles-{s}.a.ssl.fastly.net/watercolor/{z}/{x}/{y}.{ext}',
      'options' => [
        'minZoom' => 1,
        'maxZoom' => 16,
        'ext' => 'png',
        'subdomains' => 'abcd',
        'attribution' => 'Map tiles by <a href="http://stamen.com">Stamen Design</a>, <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a> &mdash; Map data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      ],
    ],
    'Stamen_Terrain' => [
      'label' => 'Stamen Terrain',
      'url' => 'http://stamen-tiles-{s}.a.ssl.fastly.net/terrain/{z}/{x}/{y}.{ext}',
      'options' => [
        'minZoom' => 4,
        'maxZoom' => 18,
        'ext' => 'png',
        'bounds' => [[22, -132], [70, -56]],
        'attribution' => 'Map tiles by <a href="http://stamen.com">Stamen Design</a>, <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a> &mdash; Map data &copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      ],
    ],
  ];

  /**
   * Leaflet Map Tile Layers Options.
   *
   * @var array
   */
  protected $leafletTileLayersOptions;

  /**
   * Google Map Types Options.
   *
   * @var array
   */
  protected $gMapTypesOptions = [
    'ROADMAP' => 'Roadmap',
    'SATELLITE' => 'Satellite',
    'HYBRID' => 'Hybrid',
    'TERRAIN' => 'Terrain',
  ];

  /**
   * GeofieldMapWidget constructor.
   *
   * {@inheritdoc}
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The Translation service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Renderer service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The Renderer service.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    TranslationInterface $string_translation,
    RendererInterface $renderer,
    EntityFieldManagerInterface $entity_field_manager,
    LinkGeneratorInterface $link_generator
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->renderer = $renderer;
    $this->entityFieldManager = $entity_field_manager;
    $this->link = $link_generator;

    foreach ($this->leafletTileLayers as $k => $tileLayer) {
      $this->leafletTileLayersOptions[$k] = $tileLayer['label'];
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('string_translation'),
      $container->get('renderer'),
      $container->get('entity_field.manager'),
      $container->get('link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'map_library' => 'gmap',
      'map_google_api_key' => '',
      'map_type_google' => 'ROADMAP',
      'map_type_leaflet' => 'OpenStreetMap_Mapnik',
      'map_type_selector' => TRUE,
      'zoom_level' => 5,
      'zoom' => [
        'start' => 5,
        'focus' => 14,
        'min' => 1,
        'max' => 17,
      ],
      'click_to_find_marker' => FALSE,
      'click_to_place_marker' => FALSE,
      'geoaddress_field' => [
        'field' => '0',
        'hidden' => FALSE,
        'disabled' => TRUE,
      ],
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['#tree'] = TRUE;

    $elements['map_google_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gmap Api Key (@link)', array(
        '@link' => $this->link->generate(t('Get a Key/Authentication for Google Maps Javascript Library'), Url::fromUri('https://developers.google.com/maps/documentation/javascript/get-api-key', array('absolute' => TRUE, 'attributes' => array('target' => 'blank')))),
      )),
      '#default_value' => $this->getSetting('map_google_api_key'),
      '#description' => $this->t('Gmap Api Key is needed for the Geocoding and Reverse Geocoding functionalities, also with Leaflet Map rendering.'),
      // @TODO: un-comment this '#required' => TRUE,
    ];

    $elements['map_library'] = array(
      '#type' => 'select',
      '#title' => $this->t('Map Library'),
      '#default_value' => $this->getSetting('map_library'),
      '#options' => array(
        'gmap' => $this->t('Google Maps'),
        'leaflet' => $this->t('Leaflet js'),
      ),
    );

    $elements['map_type_google'] = [
      '#type' => 'select',
      '#title' => $this->t('Map type'),
      '#default_value' => $this->getSetting('map_type_google'),
      '#options' => $this->gMapTypesOptions,
      '#states' => [
        'invisible' => [
          ':input[name="fields[field_geofield][settings_edit_form][settings][map_library]"]' => ['value' => 'leaflet'],
        ],
      ],
    ];

    $elements['map_type_leaflet'] = [
      '#type' => 'select',
      '#title' => $this->t('Map type'),
      '#default_value' => $this->getSetting('map_type_leaflet'),
      '#options' => $this->leafletTileLayersOptions,
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][map_library]"]' => ['value' => 'leaflet'],
        ],
      ],
    ];

    $elements['map_type_selector'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Provide a Map type Selector on the Map'),
      '#description' => $this->t('If checked, the user will be able to change Map Type throughout the selector.'),
      '#default_value' => $this->getSetting('map_type_selector'),
    ];

    $elements['zoom'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Zoom Settings'),
    );

    $elements['zoom']['start'] = array(
      '#type' => 'number',
      '#min' => 2,
      '#max' => 13,
      '#title' => $this->t('Start Zoom level'),
      '#description' => $this->t('The initial Zoom level for an empty Geofield.'),
      '#default_value' => $this->getSetting('zoom')['start'],
    );

    $elements['zoom']['focus'] = array(
      '#type' => 'number',
      '#min' => 8,
      '#max' => 16,
      '#title' => $this->t('Focus Zoom level'),
      '#description' => $this->t('The Zoom level for an assigned Geofield or for Geocoding operations results.'),
      '#default_value' => $this->getSetting('zoom')['focus'],
    );

    $elements['zoom']['min'] = array(
      '#type' => 'number',
      '#min' => 1,
      '#max' => 7,
      '#title' => $this->t('Min Zoom level'),
      '#description' => $this->t('The Min Zoom level for the Map.'),
      '#default_value' => $this->getSetting('zoom')['min'],
    );

    $elements['zoom']['max'] = array(
      '#type' => 'number',
      '#min' => 7,
      '#max' => 18,
      '#title' => $this->t('Max Zoom level'),
      '#description' => $this->t('The Max Zoom level for the Map.'),
      '#default_value' => $this->getSetting('zoom')['max'],
    );

    $elements['click_to_find_marker'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Click to Find marker'),
      '#description' => $this->t('Provides a button to recenter the map on the marker location.'),
      '#default_value' => $this->getSetting('click_to_find_marker'),
    );

    $elements['click_to_place_marker'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Click to place marker'),
      '#description' => $this->t('Provides a button to place the marker in the center location.'),
      '#default_value' => $this->getSetting('click_to_place_marker'),
    );

    $fields_list = array_merge_recursive(
      $this->entityFieldManager->getFieldMapByFieldType('string_long'),
      $this->entityFieldManager->getFieldMapByFieldType('string')
    );

    $string_fields_options = [
      '0' => $this->t('- Any -'),
      'title' => $this->t('- Title -'),
    ];

    foreach ($fields_list[$form['#entity_type']] as $k => $field) {
      if (in_array(
          $form['#bundle'], $field['bundles']) &&
        !in_array($k, ['title', 'revision_log'])) {
        $string_fields_options[$k] = $k;
      }
    }

    $elements['geoaddress_field'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Geoaddressed Field'),
    );

    $elements['geoaddress_field']['field'] = array(
      '#type' => 'select',
      '#title' => $this->t('Choose an existing field where to store the Searched / Reverse Geocoded Address'),
      '#description' => $this->t('Choose among text fields of this content type (title field excluded)'),
      '#options' => $string_fields_options,
      '#default_value' => $this->getSetting('geoaddress_field')['field'],
    );

    $elements['geoaddress_field']['hidden'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Hide</strong> this field in the Content Edit Form ?'),
      '#description' => $this->t('If checked, the selected Geoaddress Field will be Hidden to the user in the edit form, </br>and totally managed by the Geofield Reverse Geocode'),
      '#default_value' => $this->getSetting('geoaddress_field')['hidden'],
      '#states' => [
        'invisible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][geoaddress_field][field]"]' => ['value' => '0'],
        ],
      ],
    );

    $elements['geoaddress_field']['disabled'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Disable</strong> this field in the Content Edit Form ?'),
      '#description' => $this->t('If checked, the selected Geoaddress Field will be Disabled to the user in the edit form, </br>and totally managed by the Geofield Reverse Geocode'),
      '#default_value' => $this->getSetting('geoaddress_field')['disabled'],
      '#states' => [
        'invisible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][geoaddress_field][field]"]' => ['value' => '0'],
        ],
      ],
    );

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $map_library = [
      '#markup' => $this->t('Map Library: @state', array('@state' => 'gmap' == $this->getSetting('map_library') ? 'Google Maps' : 'Leaflet Js')),
    ];

    $map_type = [
      '#markup' => $this->t('Map Type: @state', array('@state' => 'leaflet' == $this->getSetting('map_library') ? $this->getSetting('map_type_leaflet') : $this->getSetting('map_type_google'))),
    ];

    $map_google_apy_key = [
      '#markup' => $this->t('Google Maps API Key: @state', array('@state' => $this->getSetting('map_google_api_key') ? $this->getSetting('map_google_api_key') : '')),
    ];

    $map_type_selector = [
      '#markup' => $this->t('Map Type Selector: @state', array('@state' => $this->getSetting('map_type_selector') ? $this->t('enabled') : $this->t('disabled'))),
    ];

    $map_zoom_levels = [
      '#markup' => $this->t('Zoom Levels -'),
    ];

    $map_zoom_levels['#markup'] .= ' ' . $this->t('Start: @state;', array('@state' => $this->getSetting('zoom')['start']));
    $map_zoom_levels['#markup'] .= ' ' . $this->t('Focus: @state;', array('@state' => $this->getSetting('zoom')['focus']));
    $map_zoom_levels['#markup'] .= ' ' . $this->t('Min: @state;', array('@state' => $this->getSetting('zoom')['min']));
    $map_zoom_levels['#markup'] .= ' ' . $this->t('Max: @state;', array('@state' => $this->getSetting('zoom')['max']));

    $html5 = [
      '#markup' => $this->t('HTML5 Geolocation button: @state', array('@state' => $this->getSetting('html5_geolocation') ? $this->t('enabled') : $this->t('disabled'))),
    ];

    $map_center = [
      '#markup' => $this->t('Click to find marker: @state', array('@state' => $this->getSetting('click_to_find_marker') ? $this->t('enabled') : $this->t('disabled'))),
    ];

    $marker_center = [
      '#markup' => $this->t('Click to place marker: @state', array('@state' => $this->getSetting('click_to_place_marker') ? $this->t('enabled') : $this->t('disabled'))),
    ];

    $geoaddress_field_field = [
      '#markup' => $this->t('Geoaddress Field: @state', array('@state' => ('0' != $this->getSetting('geoaddress_field')['field']) ? $this->getSetting('geoaddress_field')['field'] : $this->t('- any -'))),
    ];

    $geoaddress_field_hidden = [
      '#markup' => ('0' != $this->getSetting('geoaddress_field')['field']) ? $this->t('Geoaddress Field Hidden: @state', array('@state' => $this->getSetting('geoaddress_field')['hidden'])) : '',
    ];

    $geoaddress_field_disabled = [
      '#markup' => ('0' != $this->getSetting('geoaddress_field')['field']) ? $this->t('Geoaddress Field Disabled: @state', array('@state' => $this->getSetting('geoaddress_field')['disabled'])) : '',
    ];

    $container = [
      'map_library' => $map_library,
      'map_type' => $map_type,
      'map_google_apy_key' => $map_google_apy_key,
      'map_type_selector' => $map_type_selector,
      'map_zoom_levels' => $map_zoom_levels,
      'html5' => $html5,
      'map_center' => $map_center,
      'marker_center' => $marker_center,
      'field' => $geoaddress_field_field,
      'hidden' => $geoaddress_field_hidden,
      'disabled' => $geoaddress_field_disabled,
    ];

    if ('leaflet' == $this->getSetting('map_library')) {
      unset($container['map_google_apy_key']);
    }

    return $container;
  }

  /**
   * Implements \Drupal\field\Plugin\Type\Widget\WidgetInterface::formElement().
   *
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $latlon_value = array();

    foreach ($this->components as $component) {
      $latlon_value[$component] = isset($items[$delta]->{$component}) ? floatval($items[$delta]->{$component}) : '';
    }

    $element += array(
      '#type' => 'geofield_map',
      '#default_value' => $latlon_value,
      '#geolocation' => $this->getSetting('html5_geolocation'),
      '#geofield_map_geolocation_override' => $this->getSetting('html5_geolocation'),
      '#map_library' => $this->getSetting('map_library'),
      '#map_type' => 'leaflet' == $this->getSetting('map_library') ? $this->getSetting('map_type_leaflet') : $this->getSetting('map_type_google'),
      '#map_type_selector' => $this->getSetting('map_type_selector'),
      '#map_types_google' => $this->gMapTypesOptions,
      '#map_types_leaflet' => $this->leafletTileLayers,
      '#zoom' => $this->getSetting('zoom'),
      '#click_to_find_marker' => $this->getSetting('click_to_find_marker'),
      '#click_to_place_marker' => $this->getSetting('click_to_place_marker'),
      '#geoaddress_field' => $this->getSetting('geoaddress_field'),
      '#error_label' => !empty($element['#title']) ? $element['#title'] : $this->fieldDefinition->getLabel(),
      '#gmap_api_key' => $this->getSetting('map_google_api_key'),
    );

    return array('value' => $element);
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      foreach ($this->components as $component) {
        if (empty($value['value'][$component]) || !is_numeric($value['value'][$component])) {
          $values[$delta]['value'] = '';
          continue 2;
        }
      }
      $components = $value['value'];
      $values[$delta]['value'] = \Drupal::service('geofield.wkt_generator')->WktBuildPoint(array($components['lon'], $components['lat']));
    }

    return $values;
  }

}
