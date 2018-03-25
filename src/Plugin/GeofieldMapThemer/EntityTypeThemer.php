<?php

namespace Drupal\geofield_map\Plugin\GeofieldMapThemer;

use Drupal\geofield_map\MapThemerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield_map\Plugin\views\style\GeofieldGoogleMapViewStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Render\RendererInterface;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup geofield_map_themers_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @MapThemer(
 *   id = "entity_type",
 *   name = @Translation("Entity Type"),
 *   description = "this is the description for this Map Themer"
 *   type = "key_value"
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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    TranslationInterface $translation_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    RendererInterface $renderer
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory, $translation_manager);

    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->config = $config_factory;
    $this->setStringTranslation($translation_manager);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->renderer = $renderer;
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
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildMapThemerElement(array $defaults, FormStateInterface $form_state, GeofieldGoogleMapViewStyle $geofieldMapView) {

    // Get the existing (Default) Element settings.
    $default_element = $this->getDefaultThemerElement($defaults, $form_state);

    // Order the default elements based on the weight.
    // uasort($default_element, 'Drupal\Component\Utility\SortArray::sortByWeightElement');

    // Get the View Filtered entity bundles.
    $entity_type = $geofieldMapView->getViewEntityType();
    $entity_bundles = !empty($geofieldMapView->getViewFilteredBundles()) ? $geofieldMapView->getViewFilteredBundles() : array_keys($this->entityTypeBundleInfo->getBundleInfo($entity_type));

    // Reorder the entity bundles based on existing (Default) Element settings.
    if (!empty($default_element)) {
      $entity_bundles = array_merge(array_keys($default_element), $entity_bundles);
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
        $this->t('Icon Url'),
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
          '#type' => 'textfield',
          '#size' => '80',
          '#default_value' => $default_element[$bundle]['icon_url'],
          '#placeholder' => '/url_path/to_icon',
          '#element_validate' => [['Drupal\geofield_map\GeofieldMapFormElementsValidationTrait', 'urlValidate']],
        ],
        '#attributes' => ['class' => ['draggable']],
      ];
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
