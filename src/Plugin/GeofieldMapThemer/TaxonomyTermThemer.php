<?php

namespace Drupal\geofield_map\Plugin\GeofieldMapThemer;

use Drupal\geofield_map\MapThemerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield_map\Plugin\views\style\GeofieldGoogleMapViewStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\geofield_map\MarkerIconService;
use Drupal\Core\Entity\EntityInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup geofield_map_themers_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @MapThemerxxx(
 *   id = "geofieldmap_taxonomy_term",
 *   name = @Translation("Taxonomy Term (Geofield Map)"),
 *   description = "This Geofield Map Themer allows the definition of different Marker Icons based on a Taxonomy Terms reference field in View.",
 *   type = "key_value",
 *   context = "ViewStyle",
 *   defaultSettings = {
 *    "values": {}
 *   },
 * )
 */
class TaxonomyTermThemer extends MapThemerBase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation_manager
   *   The translation manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\geofield_map\MarkerIconService $marker_icon_service
   *   The Marker Icon Service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TranslationInterface $translation_manager,
    ConfigFactoryInterface $config_factory,
    RendererInterface $renderer,
    EntityTypeManagerInterface $entity_manager,
    MarkerIconService $marker_icon_service,
    EntityTypeBundleInfoInterface $entity_type_bundle_info
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $translation_manager, $renderer, $entity_manager, $marker_icon_service);
    $this->config = $config_factory;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('string_translation'),
      $container->get('config.factory'),
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('geofield_map.marker_icon'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildMapThemerElement(array $defaults, array &$form, FormStateInterface $form_state, GeofieldGoogleMapViewStyle $geofieldMapView) {

    // Get the existing (Default) Element settings.
    $default_element = $this->getDefaultThemerElement($defaults);

    // Get the View Filtered entity bundles.
    $entity_type = $geofieldMapView->getViewEntityType();
    $view_fields = $geofieldMapView->getViewFields();

    // Get the field_storage_definitions.
    $field_storage_definitions = $geofieldMapView->getEntityFieldManager()->getFieldStorageDefinitions($entity_type);

    $taxonomy_ref_fields = [];
    foreach ($view_fields as $field_key => $field_label) {
      /* @var \Drupal\field\Entity\FieldStorageConfig $field_storage */
      if ($field_storage_definitions[$field_key] instanceof FieldStorageConfig
        && $field_storage_definitions[$field_key]->getType() == 'entity_reference'
        && $field_storage_definitions[$field_key]->getSetting('target_type') == 'taxonomy_term'
        && $field_storage_definitions[$field_key]->getCardinality() == 1) {
        $taxonomy_ref_fields[$field_key] = [];
      }
    }

    $entity_bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    $view_bundles = !empty($geofieldMapView->getViewFilteredBundles()) ? $geofieldMapView->getViewFilteredBundles() : array_keys($entity_bundles);

    foreach ($taxonomy_ref_fields as $field_id => $data) {
      $taxonomy_ref_fields[$field_id]['target_bundles'] = [];
      foreach ($view_bundles as $bundle) {
        $target_bundles = array_keys($this->config->get('field.field.' . $entity_type . '.' . $bundle . '.' . $field_id)->get('settings.handler_settings.target_bundles'));
        if (!empty($target_bundles)) {
          $taxonomy_ref_fields[$field_id]['target_bundles'] = array_merge($taxonomy_ref_fields[$field_id]['target_bundles'], $target_bundles);
        }
      }
    }

    foreach ($taxonomy_ref_fields as $field_id => $data) {
      $taxonomy_ref_fields[$field_id]['terms'] = [];
      foreach ($data['target_bundles'] as $vid) {
        try {
          $taxonomy_terms = [];
          /* @var \Drupal\taxonomy\TermStorageInterface $taxonomy_term_storage */
          $taxonomy_term_storage = $this->entityManager->getStorage('taxonomy_term');
          /* @var \stdClass $term */
          foreach ($taxonomy_term_storage->loadTree($vid) as $term) {
            $taxonomy_terms[$term->tid] = $term->name . ' (vid: ' . $vid . ')';
          }
          $taxonomy_ref_fields[$field_id]['terms'] += $taxonomy_terms;
        }
        catch (InvalidPluginDefinitionException $e) {
        }
      }
    }

    // Reorder the entity bundles based on existing (Default) Element settings.
    /*    if (!empty($default_element)) {
          $weighted_bundles = [];
          foreach ($view_bundles as $bundle) {
            $weighted_bundles[$bundle] = [
              'weight' => isset($default_element[$bundle]) ? $default_element[$bundle]['weight'] : 0,
            ];
          }
          uasort($weighted_bundles, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
          $view_bundles = array_keys($weighted_bundles);
        }*/

    $element['taxonomy_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Taxonomy Field'),
      '#description' => $this->t('Chose the Taxonomy Field to base the Map Theming upon.'),
      '#options' => array_combine(array_keys($taxonomy_ref_fields), array_keys($taxonomy_ref_fields)),
      '#default_value' => !empty($default_element['taxonomy_field']) ? $default_element['taxonomy_field'] : array_shift(array_keys($taxonomy_ref_fields)),
    ];

    $element['taxonomy_field']['fields'] = [];
    foreach ($taxonomy_ref_fields as $k => $field) {

      $caption = [
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'label',
          '#value' => $this->t('Taxonomy terms from @vocabularies', [
            '@vocabularies' => implode(', ', $field['target_bundles']),
          ]),
        ],
        'caption' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('Input the Specific Icon Image path (absolute path, or relative to the Drupal site root prefixed with a trailing hash).<br>If not set, or not loadable, the Default Google Marker will be used.'),
        ],
      ];

      $element['fields'][$k] = [
        '#type' => 'container',
        'terms' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Weight'),
            $this->t('Taxonomy term'),
            $this->t('Term Alias'),
            Markup::create($this->t('Marker Icon @file_upload_help', [
              '@file_upload_help' => $this->renderer->renderPlain($this->markerIcon->getFileUploadHelp()),
            ])),
            $this->t('Icon Image Style'),
          ],
          '#tabledrag' => [[
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'terms-order-weight',
          ],
          ],
          '#caption' => $this->renderer->renderPlain($caption),
        ],
        '#states' => [
          'visible' => [
            'select[name="style_options[map_marker_and_infowindow][theming][geofieldmap_taxonomy_term][values][taxonomy_field]"]' => ['value' => $k],
          ],
        ],
      ];

      $i = 0;
      foreach ($field['terms'] as $tid => $term) {
        $fid = (integer) !empty($default_element['fields'][$k]['terms'][$tid]['icon_file']['fids']) ? $default_element['fields'][$k]['terms'][$tid]['icon_file']['fids'] : NULL;
        $element['fields'][$k]['terms'][$tid] = [
          'weight' => [
            '#type' => 'weight',
            '#title' => $this->t('Weight for @bundle', ['@bundle' => $bundle]),
            '#title_display' => 'invisible',
            '#default_value' => isset($default_element['fields'][$k]['terms'][$tid]['weight']) ? $default_element['fields'][$k]['terms'][$tid]['weight'] : $i,
            '#delta' => 20,
            '#attributes' => ['class' => ['terms-order-weight']],
          ],
          'label' => [
            '#type' => 'value',
            '#value' => $term,
            'markup' => [
              '#markup' => $term,
            ],
          ],
          'label_alias' => [
            '#type' => 'textfield',
            '#default_value' => isset($default_element['fields'][$k]['terms'][$tid]['label_alias']) ? $default_element['fields'][$k]['terms'][$tid]['label_alias'] : '',
            '#description' => $this->t('If not empty, this will be used in the legend as label alias.'),
            '#size' => 20,
          ],
          'icon_file' => $this->markerIcon->getIconFileManagedElement($fid),
          'image_style' => [
            '#type' => 'select',
            '#title' => t('Image style'),
            '#title_display' => 'invisible',
            '#options' => $this->markerIcon->getImageStyleOptions(),
            '#default_value' => isset($default_element['fields'][$k]['terms'][$tid]['image_style']) ? $default_element['fields'][$k]['terms'][$tid]['image_style'] : 'geofield_map_default_icon_style',
          ],
          '#attributes' => ['class' => ['draggable']],
        ];
        $i++;
      }

    }

    return $element;

  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(array $datum, GeofieldGoogleMapViewStyle $geofieldMapView, EntityInterface $entity, $map_theming_values) {
    $fid = NULL;
    $image_style = isset($map_theming_values[$entity->bundle()]['image_style']) ? $map_theming_values[$entity->bundle()]['image_style'] : 'none';
    if (method_exists($entity, 'bundle')) {
      $fid = isset($map_theming_values[$entity->bundle()]['icon_file']) ? $map_theming_values[$entity->bundle()]['icon_file']['fids'] : NULL;
    }
    return $this->markerIcon->getFileManagedUrl($fid, $image_style);
  }

  /**
   * {@inheritdoc}
   */
  public function getLegend(array $map_theming_values, array $configuration = []) {
    $legend = [
      '#type' => 'table',
      '#header' => [
        isset($configuration['values_label']) ? $configuration['values_label'] : $this->t('Type/Bundle'),
        isset($configuration['markers_label']) ? $configuration['markers_label'] : $this->t('Marker'),
      ],
      '#caption' => isset($configuration['legend_caption']) ? $configuration['legend_caption'] : '',
      '#attributes' => [
        'class' => ['geofield-map-legend', 'entity-type'],
      ],
    ];

    foreach ($map_theming_values as $bundle => $value) {

      // Get the icon image style, as result of the Legend configuration.
      $image_style = isset($configuration['markers_image_style']) ? $configuration['markers_image_style'] : 'none';
      // Get the map_theming_image_style, is so set.
      if (isset($configuration['markers_image_style']) && $configuration['markers_image_style'] == '_map_theming_image_style_') {
        $image_style = isset($map_theming_values[$bundle]['image_style']) ? $map_theming_values[$bundle]['image_style'] : 'none';
      }
      $fid = (integer) !empty($value['icon_file']['fids']) ? $value['icon_file']['fids'] : NULL;
      $label = isset($value['label']) ? $value['label'] : $bundle;
      $legend[$bundle] = [
        'value' => [
          '#type' => 'container',
          'label' => [
            '#markup' => !empty($value['label_alias']) ? $value['label_alias'] : $label,
          ],
          '#attributes' => [
            'class' => ['value'],
          ],
        ],
        'marker' => [
          '#type' => 'container',
          'icon_file' => !empty($fid) ? $this->markerIcon->getLegendIcon($fid, $image_style) : $this->getDefaultLegendIcon(),
          '#attributes' => [
            'class' => ['marker'],
          ],
        ],
      ];
    }

    return $legend;
  }

}