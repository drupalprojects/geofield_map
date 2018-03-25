<?php

namespace Drupal\geofield_map\Plugin\GeofieldMapThemer;

use Drupal\Component\Utility\Bytes;
use Drupal\geofield_map\MapThemerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield_map\Plugin\views\style\GeofieldGoogleMapViewStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Entity\EntityTypeManagerInterface;

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
   * The Renderer service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $renderer;

  /**
   * The Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation_manager
   *   The translation manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    TranslationInterface $translation_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    RendererInterface $renderer,
    EntityTypeManagerInterface $entity_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory, $translation_manager);

    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->config = $config_factory;
    $this->setStringTranslation($translation_manager);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->renderer = $renderer;
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('string_translation'),
      $container->get('entity_type.bundle.info'),
      $container->get('renderer'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildMapThemerElement(array $defaults, FormStateInterface $form_state, GeofieldGoogleMapViewStyle $geofieldMapView) {

    // Get the existing (Default) Element settings.
    $default_element = $this->getDefaultThemerElement($defaults, $form_state);

    // Get the View Filtered entity bundles.
    $entity_type = $geofieldMapView->getViewEntityType();
    $entity_bundles = !empty($geofieldMapView->getViewFilteredBundles()) ? $geofieldMapView->getViewFilteredBundles() : array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type));

    // Reorder the entity bundles based on existing (Default) Element settings.
    if (!empty($default_element)) {
      $weighted_bundles = [];
      foreach ($entity_bundles as $bundle) {
        $weighted_bundles[$bundle] = [
          'weight' => isset($default_element[$bundle]) ? $default_element[$bundle]['weight'] : 0,
        ];
      }
      uasort($weighted_bundles, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
      $entity_bundles = array_keys($weighted_bundles);
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

    $file_upload_validators = [
      'file_validate_extensions' => ['gif png jpg jpeg svg'],
      'file_validate_is_image' => [],
      'file_validate_size' => [Bytes::toInt('250 KB')],
    ];

    $file_upload_help = [
      '#type' => 'container',
      '#tag' => 'div',
      'file_upload_help' => [
        '#theme' => 'file_upload_help',
        '#upload_validators' => $file_upload_validators,
        '#cardinality' => 1,
      ],
      '#attributes' => [
        'style' => ['style' => 'font-size:0.8em; color: gray; text-transform: lowercase; font-weight: normal'],
      ],
    ];

    $element = [
      '#type' => 'table',
      '#header' => [
        $this->t('@entity type Type/Bundle', ['@entity type' => $entity_type]),
        $this->t('Weight'),
        Markup::create($this->t('Icon Url @file_upload_help', [
          '@file_upload_help' => $this->renderer->renderPlain($file_upload_help),
        ])),
      ],
      '#tabledrag' => [[
        'action' => 'order',
        'relationship' => 'sibling',
        'group' => 'bundles-order-weight',
      ],
      ],
      '#caption' => $this->renderer->renderRoot($caption),
    ];

    foreach ($entity_bundles as $bundle) {

      $fid = !empty($default_element[$bundle]['icon_url']) ? $default_element[$bundle]['icon_url'][0] : NULL;

      $element[$bundle] = [
        'label' => [
          '#markup' => $bundle,
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight for @bundle', ['@bundle' => $bundle]),
          '#title_display' => 'invisible',
          '#default_value' => $default_element[$bundle]['weight'],
          '#delta' => 20,
          '#attributes' => ['class' => ['bundles-order-weight']],
        ],
        'icon_url' => [
          '#type' => 'managed_file',
          '#title' => t('Choose a Marker Icon file'),
          '#title_display' => 'invisible',
          '#default_value' => !empty($fid) ? [$fid] : NULL,
          '#multiple' => FALSE,
          '#error_no_message' => FALSE,
          '#upload_location' => 'public://certfiles',
          '#upload_validators' => $file_upload_validators,
          '#progress_indicator' => 'throbber',
          '#element_validate' => [
            '\Drupal\file\Element\ManagedFile::validateManagedFile',
            [get_class($this), 'validateDefaultImageForm'],
          ],
        ],
        '#attributes' => ['class' => ['draggable']],
      ];

      if (!empty($fid)) {
        /* @var \Drupal\file\Entity\file $file */
        $file = $this->entityManager->getStorage('file')->load($fid);
        $element[$bundle]['icon_url']['preview'] = [
          '#weight' => -10,
          '#theme' => 'image_style',
          '#width' => '40px',
          '#height' => '40px',
          '#style_name' => 'thumbnail',
          '#uri' => $file->getFileUri(),
        ];
      }

    }

    return $element;

  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(array $datum, GeofieldGoogleMapViewStyle $geofieldMapView, $map_theming_values) {
    // The Custom Icon Themer plugin defines a unique icon value.
    $icon_value = $map_theming_values;
    return $icon_value;
  }

}
