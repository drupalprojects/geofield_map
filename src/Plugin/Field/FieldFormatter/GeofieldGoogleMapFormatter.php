<?php

namespace Drupal\geofield_map\Plugin\Field\FieldFormatter;

use Drupal\geofield_map\GeofieldMapFieldTrait;
use Drupal\Component\Utility\Html;
use Drupal\Core\Url;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;

/**
 * Plugin implementation of the 'geofield_google_map' formatter.
 *
 * @FieldFormatter(
 *   id = "geofield_google_map",
 *   label = @Translation("Geofield Google Map"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeofieldGoogleMapFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  use GeofieldMapFieldTrait;

  /**
   * Empty Map Options.
   *
   * @var array
   */
  protected $emptyMapOptions = [
    '0' => 'Empty field',
    '1' => 'Custom Message',
    '2' => 'Empty Map Centered at the Default Center',
  ];

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * The EntityField Manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The GeoPHPWrapper service.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $GeoPHPWrapper;

  /**
   * GeofieldGoogleMapFormatter constructor.
   *
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The Translation service.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The Entity Field Manager.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp_wrapper
   *   The The GeoPHPWrapper.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    ConfigFactoryInterface $config_factory,
    TranslationInterface $string_translation,
    LinkGeneratorInterface $link_generator,
    EntityFieldManagerInterface $entity_field_manager,
    GeoPHPInterface $geophp_wrapper
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->config = $config_factory;
    $this->link = $link_generator;
    $this->entityFieldManager = $entity_field_manager;
    $this->GeoPHPWrapper = $geophp_wrapper;
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('config.factory'),
      $container->get('string_translation'),
      $container->get('link_generator'),
      $container->get('entity_field.manager'),
      $container->get('geofield.geophp')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return self::getDefaultSettings() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings() {
    // Merge defaults before returning the array.
    if (!$this->defaultSettingsMerged) {
      $this->mergeDefaults();
    }
    return $this->settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $default_settings = self::defaultSettings();
    $settings = $this->getSettings();

    if (!empty($form['#entity_type'])){
      $entityType = $form['#entity_type'];
      $bundles = (!empty($form['#bundle'])) ? [$form['#bundle']] : [];
      unset($form['#entity_type']);  // Stops the main Gmap settings form from generating infowindow options
    }
    else if (property_exists(get_class($this), 'fieldDefinition')){
      $entityType = $this->fieldDefinition->getTargetEntityTypeId();
      $field_name = $this->fieldDefinition->getName();
      $fields = \Drupal::service('entity_field.manager')->getFieldMapByFieldType($this->fieldDefinition->getType());
      $bundles =  !empty($fields['node'][$field_name]['bundles']) ? $fields['node'][$field_name]['bundles'] : [];
    }
    if (!empty($entityType)) {
      $desc_options = [
        '0' => $this->t('- No Infowindow -'),
        'title' => $this->t('Title'),
      ];

      $fields_list = array_merge_recursive(
        \Drupal::service('entity_field.manager')->getFieldMapByFieldType('string_long'),
        \Drupal::service('entity_field.manager')->getFieldMapByFieldType('string'),
        \Drupal::service('entity_field.manager')->getFieldMapByFieldType('text'),
        \Drupal::service('entity_field.manager')->getFieldMapByFieldType('text_long')      
      );

      foreach ($fields_list[$entityType] as $k => $field) {
        if (!empty(array_intersect($field['bundles'], $bundles)) &&
          !in_array($k, ['title', 'revision_log'])) {
          $desc_options[$k] = $k;
        }
      }

      $desc_options['#rendered_entity'] = $this->t('- Rendered @entity entity -', array('@entity' => $entityType));

      $settings['infowindow_content_options'] = $desc_options;
    }

    $elements = $this->generateGMapSettingsForm($form, $form_state, $settings, $default_settings);

    if (!empty($entityType)) {
       // Get the human readable labels for the entity view modes.
      $view_mode_options = array();
      foreach (\Drupal::service('entity_display.repository')->getViewModes($entityType) as $key => $view_mode) {
        $view_mode_options[$key] = $view_mode['label'];
      }
      // The View Mode drop-down is visible conditional on "#rendered_entity"
      // being selected in the Description drop-down above.
      $elements['map_marker_and_infowindow']['view_mode'] = array(
        '#type' => 'select',
        '#title' => $this->t('View mode'),
        '#description' => $this->t('View mode the entity will be displayed in the Infowindow.'),
        '#options' => $view_mode_options,
        '#default_value' => !empty($settings['view_mode']) ? $settings['view_mode'] : 'full',
        '#states' => array(
          'visible' => array(
            ':input[name$="[settings][map_marker_and_infowindow][infowindow_field]"]' => array(
              'value' => '#rendered_entity',
            ),
          ),
        ),
      );
    }

    return $elements + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $settings = $this->getSettings();
    $gmap_api_key = $this->getGmapApiKey();

    $map_gmap_api_key = [
      '#markup' => $this->t('Google Maps API Key: @state', [
        '@state' => !empty($gmap_api_key) ? $this->link->generate($gmap_api_key, Url::fromRoute('geofield_map.settings', [], [
          'query' => [
            'destination' => Url::fromRoute('<current>')
              ->toString(),
          ],
        ])) : t("<span class='geofield-map-apikey-missing'>Gmap Api Key missing (Geocode functionalities not available).</span> @settings_page_link", [
          '@settings_page_link' => $this->link->generate(t('Set it in the Geofield Map Configuration Page'), Url::fromRoute('geofield_map.settings', [], [
            'query' => [
              'destination' => Url::fromRoute('<current>')
                ->toString(),
            ],
          ])),
        ]),
      ]),
    ];
    $map_dimensions = [
      '#markup' => $this->t('Map Dimensions: Width: @width - Height: @height', ['@width' => $settings['map_dimensions']['width'], '@height' => $settings['map_dimensions']['height']]),
    ];
    $map_empty = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Behaviour for the Empty Map: @state', ['@state' => $this->emptyMapOptions[$settings['map_empty']['empty_behaviour']]]),
    ];

    if ($settings['map_empty']['empty_behaviour'] === '1') {
      $map_empty['message'] = [
        '#markup' => $this->t('Empty Field Message: Width: @state', ['@state' => $settings['map_empty']['empty_message']]),
      ];
    }

    $map_center = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Map Default Center: @state_lat, @state_lon', [
        '@state_lat' => $settings['map_center']['lat'],
        '@state_lon' => $settings['map_center']['lon'],
      ]),
      'center_force' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Force Map Center: @state', ['@state' => $settings['map_center']['center_force'] ? $this->t('Yes') : $this->t('No')]),
      ],
    ];
    $map_zoom_and_pan = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => '<u>' . $this->t('Map Zoom and Pan:') . '</u>',
      'zoom' => [
        'initial' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('Start Zoom: @state', ['@state' => $settings['map_zoom_and_pan']['zoom']['initial']]),
        ],
        'force' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('Force Start Zoom: @state', ['@state' => $settings['map_zoom_and_pan']['zoom']['force'] ? $this->t('Yes') : $this->t('No')]),
        ],
        'min' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('Min Zoom: @state', ['@state' => $settings['map_zoom_and_pan']['zoom']['min']]),
        ],
        'max' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('Max Zoom: @state', ['@state' => $settings['map_zoom_and_pan']['zoom']['max']]),
        ],
      ],
      'scrollwheel' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Scrollwheel: @state', ['@state' => $settings['map_zoom_and_pan']['scrollwheel'] ? $this->t('Yes') : $this->t('No')]),
      ],
      'draggable' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Draggable: @state', ['@state' => $settings['map_zoom_and_pan']['draggable'] ? $this->t('Yes') : $this->t('No')]),
      ],
    ];

    // Remove the unselected array keys
    // from the map_type_control_options_type_ids.
    $map_type_control_options_type_ids = array_filter($settings['map_controls']['map_type_control_options_type_ids'], function ($value) {
      return $value !== 0;
    });

    $map_controls = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => '<u>' . $this->t('Map Controls:') . '</u>',
      'disable_default_ui' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Disable Default UI: @state', ['@state' => $settings['map_controls']['disable_default_ui'] ? $this->t('Yes') : $this->t('No')]),
      ],
      'map_type_id' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Default Map Type: @state', ['@state' => $settings['map_controls']['map_type_id']]),
      ],
    ];

    if (!$settings['map_controls']['disable_default_ui']) {
      $map_controls['zoom_control'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Zoom Control: @state', ['@state' => $settings['map_controls']['zoom_control'] ? $this->t('Yes') : $this->t('No')]),
      ];
      $map_controls['map_type_control'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Map Type Control: @state', ['@state' => $settings['map_controls']['map_type_control'] ? $this->t('Yes') : $this->t('No')]),
      ];

      $map_controls['map_type_control_options_type_ids'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $settings['map_controls']['map_type_control'] ? $this->t('Enabled Map Types: @state', ['@state' => implode(', ', array_keys($map_type_control_options_type_ids))]) : '',
      ];
      $map_controls['scale_control'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Scale Control: @state', ['@state' => $settings['map_controls']['scale_control'] ? $this->t('Yes') : $this->t('No')]),
      ];
      $map_controls['street_view_control'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Streetview Control: @state', ['@state' => $settings['map_controls']['street_view_control'] ? $this->t('Yes') : $this->t('No')]),
      ];
      $map_controls['fullscreen_control'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Fullscreen Control: @state', ['@state' => $settings['map_controls']['fullscreen_control'] ? $this->t('Yes') : $this->t('No')]),
      ];
    }

    $map_marker_and_infowindow = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => '<u>' . $this->t('Map Marker and Infowindow:') . '</u>',
      'icon_image_path' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Marker Icon: @state', ['@state' => !empty($settings['map_marker_and_infowindow']['icon_image_path']) ? $settings['map_marker_and_infowindow']['icon_image_path'] : $this->t('Default Google Marker')]),
      ],
      'infowindow_field' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Marker Infowindow @state', ['@state' => !empty($settings['map_marker_and_infowindow']['infowindow_field']) ? 'from: ' . $settings['map_marker_and_infowindow']['infowindow_field'] : $this->t('disabled')]),
      ],
    ];

    if (!empty($settings['map_additional_options'])) {
      $map_additional_options = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => '<u>' . $this->t('Map Additional Options:') . '</u>',
        'value' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $settings['map_additional_options'],
        ],
      ];
    }

    $map_markercluster = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => '<u>' . $this->t('Marker Clustering:') . '</u>',
      'markercluster_control' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Marker Cluster Enabled: @state', ['@state' => $settings['map_markercluster']['markercluster_control'] ? $this->t('Yes') : $this->t('No')]),
      ],
    ];

    if (!empty($settings['map_markercluster']['markercluster_additional_options'])) {
      $map_markercluster['markercluster_additional_options'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Marker Cluster Additional Options:'),
        'value' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $settings['map_markercluster']['markercluster_additional_options'],
        ],
      ];
    }

    $summary = [
      'map_gmap_api_key' => $map_gmap_api_key,
      'map_dimensions' => $map_dimensions,
      'map_empty' => $map_empty,
      'map_center' => $map_center,
      'map_zoom_and_pan' => $map_zoom_and_pan,
      'map_controls' => $map_controls,
      'map_marker_and_infowindow' => $map_marker_and_infowindow,
      'map_additional_options' => isset($map_additional_options) ? $map_additional_options : NULL,
      'map_markercluster' => $map_markercluster,
    ];

    // Attach Geofield Map Library.
    $summary['library'] = [
      '#attached' => [
        'library' => [
          'geofield_map/geofield_map_general',
        ],
      ],
    ];

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    /* @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $items->getEntity();
    $entity_type = $entity->bundle();
    $entity_id = $entity->id();
    /* @var \Drupal\Core\Field\FieldDefinitionInterface $field */
    $field = $items->getFieldDefinition();

    $map_settings = $this->getSettings();

    // Performs some preprocess on the maps settings before sending to js.
    $this->preProcessMapSettings($map_settings);

    $js_settings = [
      'mapid' => Html::getUniqueId("geofield_map_entity_{$entity_type}_{$entity_id}_{$field->getName()}"),
      'map_settings' => $map_settings,
      'data' => [],
    ];

    $description_field = $map_settings['map_marker_and_infowindow']['infowindow_field'];
    $description = NULL;
    // Render the entity with the selected view mode.
    if ($description_field === '#rendered_entity' && is_object($entity)) {
      $build = entity_view($entity, $map_settings['map_marker_and_infowindow']['view_mode']);
      $description = render($build);
    }
    // Normal rendering via fields.
    elseif ($description_field) {
      $description_field_name = strtolower($map_settings['map_marker_and_infowindow']['infowindow_field']);
      $description = $map_settings['map_marker_and_infowindow']['infowindow_field'] != 'title' ? $entity->$description_field_name->value : $entity->label();
    }

    $data = $this->getGeoJsonData($items, $description);

    if (empty($data) && $map_settings['map_empty']['empty_behaviour'] !== '2') {
      return [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $map_settings['map_empty']['empty_behaviour'] === '1' ? $map_settings['map_empty']['empty_message'] : '',
        '#attributes' => [
          'class' => ['empty-geofield'],
        ],
      ];
    }
    else {
      $js_settings['data'] = [
        'type' => 'FeatureCollection',
        'features' => $data,
      ];
    }
    $element = [geofield_map_googlemap_render($js_settings)];
    return $element;
  }

}
