<?php

namespace Drupal\geofield_map;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;

/**
 * Class GeofieldMapFieldTrait.
 *
 * Provide common functions for Geofield Map fields.
 *
 * @package Drupal\geofield_map
 */
trait GeofieldMapFieldTrait {

  /**
   * Google Map Types Options.
   *
   * @var array
   */
  protected $gMapTypesOptions = [
    'roadmap' => 'Roadmap',
    'satellite' => 'Satellite',
    'hybrid' => 'Hybrid',
    'terrain' => 'Terrain',
  ];

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface $this->link
   */

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
    if (method_exists(get_class($this), 'getSetting') && !empty($this->getSetting('map_google_api_key')) && empty($gmap_api_key)) {
      $gmap_api_key = $this->getSetting('map_google_api_key');
      $geofield_map_settings->set('gmap_api_key', $gmap_api_key)->save();
    }
    return $gmap_api_key;
  }

  /**
   * Get the Default Settings.
   *
   * @return array
   *   The default settings.
   */
  public static function getDefaultSettings() {
    return [
      'gmap_api_key' => '',
      'map_dimensions' => [
        'width' => '100%',
        'height' => '450px',
      ],
      'map_empty' => [
        'empty_behaviour' => '0',
        'empty_message' => t('No Geofield Value entered for this field'),
      ],
      'map_center' => [
        'lat' => '42',
        'lon' => '12.5',
        'center_force' => 0,
      ],
      'map_zoom_and_pan' => [
        'zoom' => [
          'initial' => 6,
          'force' => 0,
          'min' => 1,
          'max' => 22,
        ],
        'scrollwheel' => 1,
        'draggable' => 1,
      ],
      'map_controls' => [
        'disable_default_ui' => 0,
        'zoom_control' => 1,
        'map_type_id' => 'roadmap',
        'map_type_control' => 1,
        'map_type_control_options_type_ids' => [
          'roadmap' => 'roadmap',
          'satellite' => 'satellite',
          'hybrid' => 'hybrid',
          'terrain' => 'terrain',
        ],
        'scale_control' => 1,
        'street_view_control' => 1,
        'fullscreen_control' => 1,
      ],
      'map_marker_and_infowindow' => [
        'icon_image_path' => '',
        'infowindow_field' => 'title',
      ],
      'map_markercluster' => [
        'markercluster_control' => 0,
        'markercluster_additional_options' => '',
      ],
      'map_additional_options' => '',

    ];
  }

  /**
   * Generate the Google Map Settings Form.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $settings
   *   Form settings.
   * @param array $default_settings
   *   Default settings.
   *
   * @return array
   *   The GMap Settings Form*/
  public function generateGmapSettingsForm($form, FormStateInterface $form_state, $settings, $default_settings) {

    /* @var \Drupal\Core\Utility\LinkGeneratorInterface $link */
    $link = $this->link;

    // If it is a Field Formatter, then get the field definition.
    /* @var \Drupal\Core\Field\FieldDefinitionInterface|NULL $fieldDefinition */
    $fieldDefinition = property_exists(get_class($this), 'fieldDefinition') ? $this->fieldDefinition : NULL;

    /* @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = $this->entityFieldManager;
    $elements = [];

    // Attach Geofield Map Library.
    $elements['#attached']['library'] = [
      'geofield_map/geofield_map_general',
    ];

    $gmap_api_key = $this->getGmapApiKey();

    // If it is defined GMap API Key in the general configuration,
    // force to use it, instead.
    if (!empty($gmap_api_key)) {
      $elements['map_google_api_key'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('<strong>Gmap Api Key:</strong> @gmaps_api_key_link', [
          '@gmaps_api_key_link' => $link->generate($gmap_api_key, Url::fromRoute('geofield_map.settings', [], [
            'query' => [
              'destination' => Url::fromRoute('<current>')
                ->toString(),
            ],
          ])),
        ]),
      ];
    }
    else {
      $elements['map_google_api_key_missing'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => t("Gmap Api Key missing | The Geocode Address and ReverseGeocode functionalities won't be available.<br>@settings_page_link", [
          '@settings_page_link' => $link->generate(t('Set it in the Geofield Map Configuration Page'), Url::fromRoute('geofield_map.settings', [], [
            'query' => [
              'destination' => Url::fromRoute('<current>')
                ->toString(),
            ],
          ])),
        ]),
        '#attributes' => [
          'class' => ['geofield-map-apikey-missing'],
        ],
      ];
    }

    $elements['map_dimensions'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Dimensions'),
    ];

    $elements['map_dimensions']['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map width'),
      '#default_value' => $settings['map_dimensions']['width'],
      '#size' => 25,
      '#maxlength' => 25,
      '#description' => $this->t('The default width of a Google map, as a CSS length or percentage. Examples: <em>50px</em>, <em>5em</em>, <em>2.5in</em>, <em>95%</em>'),
      '#required' => TRUE,
    ];
    $elements['map_dimensions']['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Map height'),
      '#default_value' => $settings['map_dimensions']['height'],
      '#size' => 25,
      '#maxlength' => 25,
      '#description' => $this->t('The default height of a Google map, as a CSS length or percentage. Examples: <em>50px</em>, <em>5em</em>, <em>2.5in</em>, <em>95%</em>'),
      '#required' => TRUE,
    ];

    $elements['gmaps_api_link_markup'] = [
      '#markup' => $this->t('The following settings comply with the @gmaps_api_link.', [
        '@gmaps_api_link' => $link->generate(t('Google Maps JavaScript API Library'), Url::fromUri('https://developers.google.com/maps/documentation/javascript', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
    ];

    if (isset($fieldDefinition)) {
      $elements['map_empty'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Which behaviour for the empty map?'),
        '#description' => $this->t('If there are no entries on the map, what should be the output of field?'),
      ];
      $elements['map_empty']['empty_behaviour'] = [
        '#type' => 'select',
        '#title' => $this->t('Behaviour'),
        '#default_value' => $settings['map_empty']['empty_behaviour'],
        '#options' => $this->emptyMapOptions,
      ];
      $elements['map_empty']['empty_message'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Empty Map Message'),
        '#description' => $this->t('The message that should be rendered instead on an empty map.'),
        '#default_value' => $settings['map_empty']['empty_message'],
        '#states' => [
          'visible' => [
            ':input[name="fields[' . $fieldDefinition->getName() . '][settings_edit_form][settings][map_empty][empty_behaviour]"]' => ['value' => '1'],
          ],
        ],
      ];
    }
    else {
      $elements['map_empty']['empty_behaviour'] = [
        '#type' => 'value',
        '#value' => '1',
      ];
    }

    $elements['map_center'] = [
      '#type' => 'geofield_latlon',
      '#title' => $this->t('Default Center'),
      '#default_value' => $settings['map_center'],
      '#size' => 25,
      '#description' => $this->t('If there are no entries on the map, where should the map be centered?'),
      '#geolocation' => TRUE,
      'center_force' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Force the Map Center'),
        '#description' => $this->t('The Map will generally focus center on the input Geofields.<br>This option will instead force the Map Center notwithstanding the Geofield Values'),
        '#default_value' => $settings['map_center']['center_force'],
        '#return_value' => 1,
      ],
    ];

    $elements['map_zoom_and_pan'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Zoom and Pan'),
    ];
    $elements['map_zoom_and_pan']['zoom'] = [
      'initial' => [
        '#type' => 'number',
        '#min' => $settings['map_zoom_and_pan']['zoom']['min'],
        '#max' => $settings['map_zoom_and_pan']['zoom']['max'],
        '#title' => $this->t('Start Zoom'),
        '#default_value' => $settings['map_zoom_and_pan']['zoom']['initial'],
        '#description' => $this->t('The Initial Zoom level of the Google Map.'),
        '#element_validate' => [[get_class($this), 'zoomLevelValidate']],
      ],
      'force' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Force the Start Zoom'),
        '#description' => $this->t('In case of multiple GeoMarkers, the Map will naturally focus zoom on the input Geofields bounds.<br>This option will instead force the Map Zoom on the input Start Zoom value'),
        '#default_value' => $settings['map_zoom_and_pan']['zoom']['force'],
        '#return_value' => 1,
      ],
      'min' => [
        '#type' => 'number',
        '#min' => isset($default_settings['map_zoom_and_pan']['default']) ? $default_settings['map_zoom_and_pan']['default']['zoom']['min'] : $default_settings['map_zoom_and_pan']['zoom']['min'],
        '#max' => $settings['map_zoom_and_pan']['zoom']['max'],
        '#title' => $this->t('Min Zoom Level'),
        '#default_value' => $settings['map_zoom_and_pan']['zoom']['min'],
        '#description' => $this->t('The Minimum Zoom level for the Map.'),
      ],
      'max' => [
        '#type' => 'number',
        '#min' => $settings['map_zoom_and_pan']['zoom']['min'],
        '#max' => isset($default_settings['map_zoom_and_pan']['default']) ? $default_settings['map_zoom_and_pan']['default']['zoom']['max'] : $default_settings['map_zoom_and_pan']['zoom']['max'],
        '#title' => $this->t('Max Zoom Level'),
        '#default_value' => $settings['map_zoom_and_pan']['zoom']['max'],
        '#description' => $this->t('The Maximum Zoom level for the Map.'),
        '#element_validate' => [[get_class($this), 'maxZoomLevelValidate']],
      ],
    ];
    $elements['map_zoom_and_pan']['scrollwheel'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Scrollwheel'),
      '#description' => $this->t('Enable scrollwheel zooming'),
      '#default_value' => $settings['map_zoom_and_pan']['scrollwheel'],
      '#return_value' => 1,
    ];
    $elements['map_zoom_and_pan']['draggable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Draggable'),
      '#description' => $this->t('Enable dragging/panning on the map'),
      '#default_value' => $settings['map_zoom_and_pan']['draggable'],
      '#return_value' => 1,
    ];

    if (isset($fieldDefinition)) {
      $disable_default_ui_selector = ':input[name="fields[' . $fieldDefinition->getName() . '][settings_edit_form][settings][map_controls][disable_default_ui]"]';
    }
    else {
      $disable_default_ui_selector = ':input[name="style_options[map_controls][disable_default_ui]"]';
    }

    $elements['map_controls'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Controls'),
    ];
    $elements['map_controls']['disable_default_ui'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable Default UI'),
      '#description' => $this->t('This property disables any automatic UI behavior and Control from the Google Map'),
      '#default_value' => $settings['map_controls']['disable_default_ui'],
      '#return_value' => 1,
    ];
    $elements['map_controls']['zoom_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Zoom Control'),
      '#description' => $this->t('The enabled/disabled state of the Zoom control.'),
      '#default_value' => $settings['map_controls']['zoom_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ],
    ];
    $elements['map_controls']['map_type_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Default Map Type'),
      '#default_value' => $settings['map_controls']['map_type_id'],
      '#options' => $this->gMapTypesOptions,
    ];
    $elements['map_controls']['map_type_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled Map Type Control'),
      '#description' => $this->t('The initial enabled/disabled state of the Map type control.'),
      '#default_value' => $settings['map_controls']['map_type_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ],
    ];
    $elements['map_controls']['map_type_control_options_type_ids'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('The enabled Map Types'),
      '#description' => $this->t('The Map Types that will be available in the Map Type Control.'),
      '#default_value' => $settings['map_controls']['map_type_control_options_type_ids'],
      '#options' => $this->gMapTypesOptions,
      '#return_value' => 1,
    ];

    if (isset($fieldDefinition)) {
      $elements['map_controls']['map_type_control_options_type_ids']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $fieldDefinition->getName() . '][settings_edit_form][settings][map_controls][map_type_control]"]' => ['checked' => TRUE],
        ],
        'invisible' => [
          $disable_default_ui_selector => ['checked' => TRUE],
        ],
      ];
    }
    else {
      $elements['map_controls']['map_type_control_options_type_ids']['#states'] = [
        'visible' => [
          ':input[name="style_options[map_controls][map_type_control]"]' => ['checked' => TRUE],
        ],
        'invisible' => [
          $disable_default_ui_selector => ['checked' => TRUE],

        ],
      ];
    }

    $elements['map_controls']['scale_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Scale Control'),
      '#description' => $this->t('Show map scale'),
      '#default_value' => $settings['map_controls']['scale_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ]
    ];
    $elements['map_controls']['street_view_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Streetview Control'),
      '#description' => $this->t('Enable the Street View functionality on the Map.'),
      '#default_value' => $settings['map_controls']['street_view_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ],
    ];
    $elements['map_controls']['fullscreen_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fullscreen Control'),
      '#description' => $this->t('Enable the Fullscreen View of the Map.'),
      '#default_value' => $settings['map_controls']['fullscreen_control'],
      '#return_value' => 1,
      '#states' => [
        'visible' => [
          $disable_default_ui_selector => ['checked' => FALSE],
        ],
      ],
    ];

    $elements['map_marker_and_infowindow'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Map Marker and Infowindow'),
    ];
    $elements['map_marker_and_infowindow']['icon_image_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon Image'),
      '#size' => '120',
      '#description' => $this->t('Input the Specific Icon Image path (absolute or relative to the Drupal site root). If not set, the Default Google Marker will be used.'),
      '#default_value' => $settings['map_marker_and_infowindow']['icon_image_path'],
      '#placeholder' => 'https://developers.google.com/maps/documentation/javascript/examples/full/images/beachflag.png',
      '#element_validate' => [[get_class($this), 'urlValidate']],
    ];

    // In case it is a Field Formatter.
    if (isset($form['#entity_type'])) {

      $fields_list = array_merge_recursive(
        $entityFieldManager->getFieldMapByFieldType('string_long'),
        $entityFieldManager->getFieldMapByFieldType('string')
      );

      $string_fields_options = [
        '0' => $this->t('- Any - No Infowindow'),
        'title' => $this->t('- Title -'),
      ];

      foreach ($fields_list[$form['#entity_type']] as $k => $field) {
        if (in_array(
            $form['#bundle'], $field['bundles']) &&
          !in_array($k, ['title', 'revision_log'])) {
          $string_fields_options[$k] = $k;
        }
      }

      $info_window_source_options = $string_fields_options;

    }
    else {
      $info_window_source_options = $settings['infowindow_content_options'];
    }

    $elements['map_marker_and_infowindow']['infowindow_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Marker Infowindow Content from'),
      '#description' => $this->t('Choose an existing string type field from which populate the Marker Infowindow'),
      '#options' => $info_window_source_options,
      '#default_value' => $settings['map_marker_and_infowindow']['infowindow_field'],
    ];

    $elements['map_additional_options'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('Map Additional Options'),
      '#description' => $this->t('<strong>These will override the above settings</strong><br>An object literal of additional map options, that comply with the Google Maps JavaScript API. The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.<br>It is even possible to input Map Control Positions. For this use the numeric values of the google.maps.ControlPosition, otherwise the option will be passed as incomprehensible string to Google Maps API.'),
      '#default_value' => $settings['map_additional_options'],
      '#placeholder' => $this->t('{"disableDoubleClickZoom": "cooperative",
"gestureHandling": "none",
"streetViewControlOptions": {"position": 5}
}'),
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];

    $elements['map_markercluster'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Marker Clustering'),
    );
    $elements['map_markercluster']['markup'] = [
      '#markup' => $this->t('Enable the functionality of the @markeclusterer_api_link.', [
        '@markeclusterer_api_link' => $link->generate(t('Marker Clusterer Google Maps JavaScript Library'), Url::fromUri('https://github.com/googlemaps/js-marker-clusterer', [
          'absolute' => TRUE,
          'attributes' => ['target' => 'blank'],
        ])),
      ]),
    ];
    $elements['map_markercluster']['markercluster_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Marker Clustering'),
      '#default_value' => $settings['map_markercluster']['markercluster_control'],
      '#return_value' => 1,
    ];
    $elements['map_markercluster']['markercluster_additional_options'] = [
      '#type' => 'textarea',
      '#rows' => 5,
      '#title' => $this->t('Marker Cluster Additional Options'),
      '#description' => $this->t('An object literal of additional marker cluster options, that comply with the Marker Clusterer Google Maps JavaScript Library. The syntax should respect the javascript object notation (json) format.<br>As suggested in the field placeholder, always use double quotes (") both for the indexes and the string values.'),
      '#default_value' => $settings['map_markercluster']['markercluster_additional_options'],
      '#placeholder' => $this->t('{"maxZoom": 12, "gridSize": 25, "imagePath": "modules/custom/geofield_map/images/m"}'),
      '#element_validate' => [[get_class($this), 'jsonValidate']],
    ];

    if (isset($fieldDefinition)) {
      $elements['map_markercluster']['markercluster_additional_options']['#states'] = [
        'visible' => [
          ':input[name="fields[' . $fieldDefinition->getName() . '][settings_edit_form][settings][map_markercluster][markercluster_control]"]' => ['checked' => TRUE],
        ],
      ];
    }
    else {
      $elements['map_markercluster']['markercluster_additional_options']['#states'] = [
        'visible' => [
          ':input[name="style_options[map_markercluster][markercluster_control]"]' => ['checked' => TRUE],
        ],
      ];
    }

    return $elements;

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

  /**
   * Pre Process the MapSettings.
   *
   * Performs some preprocess on the maps settings before sending to js.
   *
   * @param array $map_settings
   *   The map settings.
   */
  protected function preProcessMapSettings(&$map_settings) {
    // Set the gmap_api_key as map settings.
    $map_settings['gmap_api_key'] = $this->getGmapApiKey();

    // Transform into simple array values the map_type_control_options_type_ids.
    $map_settings['map_controls']['map_type_control_options_type_ids'] = array_keys(array_filter($map_settings['map_controls']['map_type_control_options_type_ids'], function ($value) {
      return $value !== 0;
    }));

    // Generate Absolute icon_image_path, if it is not.
    $icon_image_path = $map_settings['map_marker_and_infowindow']['icon_image_path'];
    if (!empty($icon_image_path) && !UrlHelper::isExternal($map_settings['map_marker_and_infowindow']['icon_image_path'])) {
      $map_settings['map_marker_and_infowindow']['icon_image_path'] = Url::fromUri('base:' . $icon_image_path, ['absolute' => TRUE])
        ->toString();
    }
  }


  /**
   * Transform Geofield data into Geojson features.
   *
   * @param array $items
   *   The Geofield Data Values.
   * @param string $description
   *   The description value.
   */
  protected function getGeoJsonData($items, $description = NULL) {
    $data = [];
    foreach ($items as $delta => $item) {

      /* @var \Point $geometry */
      $geometry = $this->GeoPHPWrapper->load(is_a($item, '\Drupal\geofield\Plugin\Field\FieldType\GeofieldItem') ? $item->value : $item);
      if (!empty($geometry)) {
        $datum = [
          "type" => "Feature",
          "geometry" => json_decode($geometry->out('json')),
        ];
        $datum['properties'] = [
          'description' => isset($description) ? $description : NULL,
        ];
        $data[] = $datum;
      }
    }
    return $data;
  }

}
