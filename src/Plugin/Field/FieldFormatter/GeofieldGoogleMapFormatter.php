<?php

namespace Drupal\geofield_map\Plugin\Field\FieldFormatter;

use Drupal\geofield_map\GeofieldMapFieldTrait;
use Drupal\geofield_map\GeofieldMapFormElementsValidationTrait;
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
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\Markup;
use Drupal\geofield_map\MarkerIconService;

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
  use GeofieldMapFormElementsValidationTrait;

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
   * Entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;


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
   * The geoPhpWrapper service.
   *
   * @var \Drupal\geofield\GeoPHP\GeoPHPInterface
   */
  protected $geoPhpWrapper;

  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $renderer;

  /**
   * The Icon Managed File Service.
   *
   * @var \Drupal\geofield_map\MarkerIconService
   */
  protected $markerIcon;

  /**
   * GeofieldGoogleMapFormatter constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The Translation service.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   Entity display repository service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The Entity Field Manager.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp_wrapper
   *   The The geoPhpWrapper.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Renderer service.
   * @param \Drupal\geofield_map\MarkerIconService $marker_icon_service
   *   The Marker Icon Service.
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
    EntityTypeManagerInterface $entity_type_manager,
    EntityDisplayRepositoryInterface $entity_display_repository,
    EntityFieldManagerInterface $entity_field_manager,
    GeoPHPInterface $geophp_wrapper,
    RendererInterface $renderer,
    MarkerIconService $marker_icon_service
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->config = $config_factory;
    $this->link = $link_generator;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityFieldManager = $entity_field_manager;
    $this->geoPhpWrapper = $geophp_wrapper;
    $this->renderer = $renderer;
    $this->markerIcon = $marker_icon_service;
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
      $container->get('entity_type.manager'),
      $container->get('entity_display.repository'),
      $container->get('entity_field.manager'),
      $container->get('geofield.geophp'),
      $container->get('renderer'),
      $container->get('geofield_map.marker_icon')
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

    // Define a specific default_icon_image_mode that consider icon_image_path
    // eventually set previously to its select introduction.
    $default_icon_image_mode = !empty($settings['map_marker_and_infowindow']['icon_image_path']) ? 'icon_image_path' : $default_settings['map_marker_and_infowindow']['icon_image_mode'];

    $elements = $this->generateGMapSettingsForm($form, $form_state, $settings, $default_settings);

    $elements['#attached'] = [
      'library' => [
        'geofield_map/geofield_map_view_display_settings',
      ],
    ];

    $elements['map_marker_and_infowindow']['icon_image_mode'] = [
      '#title' => $this->t('Custom Icon definition mode'),
      '#type' => 'select',
      '#options' => [
        'icon_file' => 'Icon File',
        'icon_image_path' => 'Icon Image Path',
      ],
      '#default_value' => !empty($settings['map_marker_and_infowindow']['icon_image_mode']) ? $settings['map_marker_and_infowindow']['icon_image_mode'] : $default_icon_image_mode,
      '#description' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => Markup::create('choose method between:<br><b>Icon Image Path:</b> Point the image url (absolute or relative to Drupal root folder)<br><b>Icon Image File:</b> Upload an Icon Image into Drupal application</li>'),
      ],
      '#weight' => $elements['map_marker_and_infowindow']['icon_image_path']['#weight'] - 2,
    ];

    $elements['map_marker_and_infowindow']['icon_image_path']['#states'] = [
      'visible' => [
        'select[name="fields[field_geofield][settings_edit_form][settings][map_marker_and_infowindow][icon_image_mode]"]' => ['value' => 'icon_image_path'],
      ],
    ];

    $file_upload_help = $this->markerIcon->getFileUploadHelp();
    $fid = (integer) !empty($settings['map_marker_and_infowindow']['icon_file_wrapper']['icon_file']['fids']) ? $settings['map_marker_and_infowindow']['icon_file_wrapper']['icon_file']['fids'] : NULL;
    $elements['map_marker_and_infowindow']['icon_file_wrapper'] = [
      '#type' => 'container',
      'label' => [
        '#markup' => Markup::create($this->t('<label>Custom Icon Image File</label>')),
      ],
      'description' => [
        '#markup' => Markup::create($this->t('The chosen icon file will be used as Marker for this content @file_upload_help', [
          '@file_upload_help' => $this->renderer->renderPlain($file_upload_help),
        ])),
      ],
      'icon_file' => $this->markerIcon->getIconFileManagedElement($fid),
      'image_style' => [
        '#type' => 'select',
        '#title' => t('Image style'),
        '#options' => $this->markerIcon->getImageStyleOptions(),
        '#default_value' => isset($settings['map_marker_and_infowindow']['icon_file_wrapper']['image_style']) ? $settings['map_marker_and_infowindow']['icon_file_wrapper']['image_style'] : 'geofield_map_default_icon_style',
      ],
      '#states' => [
        'visible' => [
          'select[name="fields[field_geofield][settings_edit_form][settings][map_marker_and_infowindow][icon_image_mode]"]' => ['value' => 'icon_file'],
        ],
      ],
      '#weight' => $elements['map_marker_and_infowindow']['icon_image_mode']['#weight'] + 1,
    ];

    return $elements + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $default_settings = self::defaultSettings();
    $settings = $this->getSettings();

    // Define a specific default_icon_image_mode that consider icon_image_path
    // eventually set previously to its select introduction.
    $default_icon_image_mode = !empty($settings['map_marker_and_infowindow']['icon_image_path']) ? 'icon_image_path' : $default_settings['map_marker_and_infowindow']['icon_image_mode'];

    $gmap_api_key = $this->getGmapApiKey();

    // Define the Google Maps API Key value message string.
    if (!empty($gmap_api_key)) {
      $state = $this->link->generate($gmap_api_key, Url::fromRoute('geofield_map.settings', [], [
        'query' => [
          'destination' => Url::fromRoute('<current>')
            ->toString(),
        ],
      ]));
    }
    else {
      $state = $this->t("<span class='geofield-map-warning'>Gmap Api Key missing<br>Google Maps functionality may not be available.</span> @settings_page_link", [
        '@settings_page_link' => $this->link->generate($this->t('Set it in the Geofield Map Configuration Page'), Url::fromRoute('geofield_map.settings', [], [
          'query' => [
            'destination' => Url::fromRoute('<current>')
              ->toString(),
          ],
        ])),
      ]);
    }

    $map_gmap_api_key = [
      '#markup' => $this->t('Google Maps API Key: @state', [
        '@state' => $state,
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
      'map_reset' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Map Reset Control: @state', ['@state' => !empty($settings['map_zoom_and_pan']['map_reset']) ? $this->t('Yes') : $this->t('No')]),
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

    $icon_image_mode = !empty($settings['map_marker_and_infowindow']['icon_image_mode']) ? $settings['map_marker_and_infowindow']['icon_image_mode'] : $default_icon_image_mode;
    $map_marker_and_infowindow = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => '<u>' . $this->t('Map Marker and Infowindow:') . '</u>',
      'icon_image_mode' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Marker Custom Icon definition mode: @state', ['@state' => $icon_image_mode]),
        '#weight' => 0,
      ],
      'infowindow_field' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Marker Infowindow @state', ['@state' => !empty($settings['map_marker_and_infowindow']['infowindow_field']) ? 'from: ' . $settings['map_marker_and_infowindow']['infowindow_field'] : $this->t('disabled')]),
        '#weight' => 2,
      ],
      'force_open' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Open Infowindow on Load: @state', ['@state' => $settings['map_marker_and_infowindow']['force_open'] ? $this->t('Yes') : $this->t('No')]),
        '#weight' => 3,
      ],
    ];

    if ($icon_image_mode == 'icon_image_path') {
      $map_marker_and_infowindow['icon_image_path'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Marker Icon: @state', ['@state' => !empty($settings['map_marker_and_infowindow']['icon_image_path']) ? $settings['map_marker_and_infowindow']['icon_image_path'] : $this->t('Default Google Marker')]),
        '#weight' => 1,
      ];
    }

    if ($settings['map_marker_and_infowindow']['infowindow_field'] == '#rendered_entity') {
      $map_marker_and_infowindow['view_mode'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('View Mode: @state', ['@state' => $settings['map_marker_and_infowindow']['view_mode']]),
      ];
    }

    if (!empty($settings['map_additional_options'])) {
      $map_additional_options = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Map Additional Options:'),
        'value' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $settings['map_additional_options'],
        ],
      ];
    }

    $map_oms = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => '<u>' . $this->t('Overlapping Markers:') . '</u>',
      'map_oms_control' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Spiderfy overlapping markers: @state', ['@state' => $settings['map_oms']['map_oms_control'] ? $this->t('Yes') : $this->t('No')]),
      ],
    ];

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

    $custom_style_map = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Custom Style Map: @state', ['@state' => $settings['custom_style_map']['custom_style_control'] ? $this->t('Yes') : $this->t('No')]),
    ];

    if ($settings['custom_style_map']['custom_style_control']) {
      $custom_style_map['custom_style_name'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Custom Style Name: @state', ['@state' => $settings['custom_style_map']['custom_style_name']]),
      ];
      $custom_style_map['custom_style_default'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Custom Map Style as Default: @state', ['@state' => $settings['custom_style_map']['custom_style_default'] ? $this->t('Yes') : $this->t('No')]),
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
      'map_oms' => $map_oms,
      'map_markercluster' => $map_markercluster,
      'custom_style_map' => $custom_style_map,
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

    // This avoids the infinite loop by stopping the display
    // of any map embedded in an infowindow.
    $view_in_progress = &drupal_static(__FUNCTION__);
    if ($view_in_progress) {
      return [];
    }
    $view_in_progress = TRUE;

    /* @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $items->getEntity();
    // Take the entity translation, if existing.
    /* @var \Drupal\Core\TypedData\TranslatableInterface $entity */
    if ($entity->hasTranslation($langcode)) {
      $entity = $entity->getTranslation($langcode);
    }
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $entity_id = $entity->id();
    /* @var \Drupal\Core\Field\FieldDefinitionInterface $field */
    $field = $items->getFieldDefinition();

    $map_settings = $this->getSettings();

    // Performs some preprocess on the maps settings before sending to js.
    $this->preProcessMapSettings($map_settings);

    $js_settings = [
      'mapid' => Html::getUniqueId("geofield_map_entity_{$bundle}_{$entity_id}_{$field->getName()}"),
      'map_settings' => $map_settings,
      'data' => [],
    ];

    $description = [];
    $description_field = isset($map_settings['map_marker_and_infowindow']['infowindow_field']) ? $map_settings['map_marker_and_infowindow']['infowindow_field'] : NULL;
    /* @var \Drupal\Core\Field\FieldItemList $description_field_entity */
    $description_field_entity = $entity->$description_field;

    // Render the entity with the selected view mode.
    if (isset($description_field) && $description_field === '#rendered_entity' && is_object($entity)) {
      $build = $this->entityTypeManager->getViewBuilder($entity_type)->view($entity, $map_settings['map_marker_and_infowindow']['view_mode']);
      $description[] = $this->renderer->renderPlain($build);
    }
    // Normal rendering via fields.
    elseif (isset($description_field)) {
      if ($map_settings['map_marker_and_infowindow']['infowindow_field'] === 'title') {
        $description[] = $entity->label();
      }
      elseif (isset($entity->$description_field)) {
        $description_field_cardinality = $description_field_entity->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
        foreach ($description_field_entity->getValue() as $value) {
          $description[] = isset($value['value']) ? $value['value'] : '';
          if ($description_field_cardinality == 1 || $map_settings['map_marker_and_infowindow']['multivalue_split'] == FALSE) {
            break;
          }
        }
      }
    }

    $geojson_data = $this->getGeoJsonData($items, $description);

    // Add Custom Icon File, if set.
    if (isset($map_settings['map_marker_and_infowindow']['icon_image_mode'])
      && $map_settings['map_marker_and_infowindow']['icon_image_mode'] == 'icon_file'
    ) {
      $image_style = isset($map_settings['map_marker_and_infowindow']['icon_file_wrapper']['image_style']) ? $map_settings['map_marker_and_infowindow']['icon_file_wrapper']['image_style'] : 'none';
      $fid = (integer) !empty($map_settings['map_marker_and_infowindow']['icon_file_wrapper']['icon_file']['fids']) ? $map_settings['map_marker_and_infowindow']['icon_file_wrapper']['icon_file']['fids'] : NULL;
      foreach ($geojson_data as $k => $datum) {
        $geojson_data[$k]['properties']['icon'] = $this->markerIcon->getFileManagedUrl($fid, $image_style);
        // Flag the data with theming, for later rendering logic.
        $geojson_data[$k]['properties']['theming'] = TRUE;
      }
    }

    if (empty($geojson_data) && $map_settings['map_empty']['empty_behaviour'] !== '2') {
      $view_in_progress = FALSE;
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
        'features' => $geojson_data,
      ];
    }
    $element = [geofield_map_googlemap_render($js_settings)];

    // Part of infinite loop stopping strategy.
    $view_in_progress = FALSE;

    return $element;
  }

}
