<?php

namespace Drupal\geofield_map\Plugin\GeofieldMapThemer;

use Drupal\geofield_map\MapThemerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield_map\Plugin\views\style\GeofieldGoogleMapViewStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\geofield_map\IconFileService;
use Drupal\Core\Entity\EntityInterface;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup geofield_map_themers_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @MapThemer(
 *   id = "geofieldmap_entity_type",
 *   name = @Translation("Geofield Map Entity Type"),
 *   description = "This Geofield Map Themer allows the definition of different Marker Icons based on the View filetered Entity Types/Bundles.",
 *   type = "key_value",
 *   defaultSettings = {
 *    "values": {}
 *   },
 * )
 */
class EntityTypeThemer extends MapThemerBase {

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
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\geofield_map\IconFileService $icon_file_service
   *   The Icon File Service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    TranslationInterface $translation_manager,
    RendererInterface $renderer,
    EntityTypeManagerInterface $entity_manager,
    IconFileService $icon_file_service,
    EntityTypeBundleInfoInterface $entity_type_bundle_info
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $translation_manager, $renderer, $entity_manager, $icon_file_service);
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
      $container->get('renderer'),
      $container->get('entity_type.manager'),
      $container->get('geofield_map.icon_file'),
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
    $entity_bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    $view_bundles = !empty($geofieldMapView->getViewFilteredBundles()) ? $geofieldMapView->getViewFilteredBundles() : array_keys($entity_bundles);

    // Reorder the entity bundles based on existing (Default) Element settings.
    if (!empty($default_element)) {
      $weighted_bundles = [];
      foreach ($view_bundles as $bundle) {
        $weighted_bundles[$bundle] = [
          'weight' => isset($default_element[$bundle]) ? $default_element[$bundle]['weight'] : 0,
        ];
      }
      uasort($weighted_bundles, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
      $view_bundles = array_keys($weighted_bundles);
    }

    $caption = [
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'label',
        '#value' => $this->t('Icon Urls, per Entity Types'),
      ],
      'caption' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('Input the Specific Icon Image path (absolute path, or relative to the Drupal site root prefixed with a trailing hash).<br>If not set, or not loadable, the Default Google Marker will be used.'),
      ],
    ];

    $element = [
      '#type' => 'table',
      '#header' => [
        $this->t('@entity type Type/Bundle', ['@entity type' => $entity_type]),
        $this->t('Weight'),
        Markup::create($this->t('Marker Icon @file_upload_help', [
          '@file_upload_help' => $this->renderer->renderPlain($this->iconFile->getFileUploadHelp()),
        ])),
      ],
      '#tabledrag' => [[
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'bundles-order-weight',
      ],
      ],
      '#caption' => $this->renderer->renderPlain($caption),
    ];

    foreach ($view_bundles as $bundle) {

      $fid = (integer) !empty($default_element[$bundle]['icon_file']['fids']) ? $default_element[$bundle]['icon_file']['fids'] : NULL;
      $element[$bundle] = [
        'label' => [
          '#type' => 'value',
          '#value' => $entity_bundles[$bundle]['label'],
          'markup' => [
            '#markup' => $entity_bundles[$bundle]['label'],
          ],
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @bundle', ['@bundle' => $bundle]),
          '#title_display' => 'invisible',
          '#default_value' => $default_element[$bundle]['weight'],
          '#delta' => 20,
          '#attributes' => ['class' => ['bundles-order-weight']],
        ],
        'icon_file' => $this->iconFile->getIconFileManagedElement($fid),
        '#attributes' => ['class' => ['draggable']],
      ];

    }

    return $element;

  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(array $datum, GeofieldGoogleMapViewStyle $geofieldMapView, EntityInterface $entity, $map_theming_values) {
    $fid = NULL;
    if (method_exists($entity, 'bundle')) {
      $fid = $map_theming_values[$entity->bundle()]['icon_file']['fids'];
    }
    return $this->iconFile->getFileManagedUrl($fid);
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
      $fid = (integer) $value['icon_file']['fids'] ? $value['icon_file']['fids'] : NULL;
      $legend[$bundle] = [
        'value' => [
          '#type' => 'container',
          'label' => [
            '#markup' => isset($value['label']) ? $value['label'] : $bundle,
          ],
          '#attributes' => [
            'class' => ['value'],
          ],
        ],
        'marker' => [
          '#type' => 'container',
          'icon_file' => !empty($fid) ? $this->iconFile->getIconThumbnail($fid) : $this->getDefaultLegendIcon(),
          '#attributes' => [
            'class' => ['marker'],
          ],
        ],
      ];

    }

    return $legend;
  }

}
