<?php

namespace Drupal\geofield_map\Plugin\views\style;

use Drupal\geofield_map\GeofieldMapFieldTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\views\ViewExecutable;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup views_style_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @ViewsStyle(
 *   id = "geofield_google_map",
 *   title = @Translation("Geofield Google Map"),
 *   help = @Translation("Displays a View as a Geofield Google Map."),
 *   display_types = {"normal"},
 *   theme = "geofield-google-map"
 * )
 */
class GeofieldGoogleMapViewStyle extends StylePluginBase implements ContainerFactoryPluginInterface {

  use GeofieldMapFieldTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The Entity type property.
   *
   * @var string
   */
  private $entityType;

  /**
   * The Entity Info service property.
   *
   * @var string
   */
  private $entityInfo;

  /**
   * Does the style plugin for itself support to add fields to it's output.
   *
   * @var bool
   */
  protected $usesFields = TRUE;

  /**
   * The Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  /**
   * The Entity Field manager service property.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The Entity Display Repository service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplay;

  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $renderer;

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;

  /**
   * Constructs a GeofieldGoogleMapView style instance.
   *
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param EntityDisplayRepositoryInterface $entity_display
   *   The entity display manager.
   * @param RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_manager,
    EntityFieldManagerInterface $entity_field_manager,
    EntityDisplayRepositoryInterface $entity_display,
    RendererInterface $renderer,
    LinkGeneratorInterface $link_generator
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $entity_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityDisplay = $entity_display;
    $this->config = $config_factory;
    $this->renderer = $renderer;
    $this->link = $link_generator;

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
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('entity_display.repository'),
      $container->get('renderer'),
      $container->get('link_generator')
    );
  }

  /**
   * If this view is displaying an entity, save the entity type and info.
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    // For later use, set entity info related to the View's base table.
    $base_tables = array_keys($view->getBaseTables());
    $base_table = reset($base_tables);
    foreach ($this->entityManager->getDefinitions() as $key => $info) {
      if ($info->getDataTable() == $base_table) {
        $this->entityType = $key;
        $this->entityInfo = $info;
        return;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function evenEmpty() {
    // Render map even if there is no data.
    return TRUE;
  }

  /**
   * Options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $default_settings = self::defineOptions();

    // Get a list of fields and a sublist of geo data fields in this view.
    $fields = array();
    $fields_geo_data = array();
    /* @var \Drupal\views\Plugin\views\ViewsHandlerInterface $handler) */
    foreach ($this->displayHandler->getHandlers('field') as $field_id => $handler) {
      $label = $handler->adminLabel() ?: $field_id;
      $fields[$field_id] = $label;
      if (is_a($handler, '\Drupal\views\Plugin\views\field\EntityField')) {
        /* @var \Drupal\views\Plugin\views\field\EntityField $handler */
        $field_storage_definitions = $this->entityFieldManager
          ->getFieldStorageDefinitions($handler->getEntityType());
        $field_storage_definition = $field_storage_definitions[$handler->definition['field_name']];

        if ($field_storage_definition->getType() == 'geofield') {
          $fields_geo_data[$field_id] = $label;
        }
      }
    }

    // Check whether we have a geo data field we can work with.
    if (!count($fields_geo_data)) {
      $form['error'] = array(
        '#markup' => $this->t('Please add at least one geofield to the view.'),
      );
      return;
    }

    // Map data source.
    $form['data_source'] = array(
      '#type' => 'select',
      '#title' => $this->t('Data Source'),
      '#description' => $this->t('Which field contains geodata?'),
      '#options' => $fields_geo_data,
      '#default_value' => $this->options['data_source'],
      '#required' => TRUE,
    );

    // Name field.
/*    $form['name_field'] = array(
      '#type' => 'select',
      '#title' => $this->t('Title Field'),
      '#description' => $this->t('Choose the field which will appear as a title on tooltips.'),
      '#options' => array_merge(array('' => ''), $fields),
      '#default_value' => $this->options['name_field'],
    );*/

    $desc_options = array_merge(array('0' => $this->t('- Any - No Infowindow')), $fields);
    // Add an option to render the entire entity using a view mode.
    if ($this->entityType) {
      $desc_options += array(
        '#rendered_entity' => $this->t('< @entity entity >', array('@entity' => $this->entityType)),
      );
    }

    $this->options['infowindow_content_options'] = $desc_options;

/*    $form['description_field'] = array(
      '#type' => 'select',
      '#title' => $this->t('Description Field'),
      '#description' => $this->t('Choose the field or rendering method which will appear as a description on tooltips or popups.'),
      '#required' => FALSE,
      '#options' => $desc_options,
      '#default_value' => $this->options['description_field'],
    );*/

    if ($this->entityType) {

      // Get the human readable labels for the entity view modes.
      $view_mode_options = array();
      foreach ($this->entityDisplay->getViewModes($this->entityType) as $key => $view_mode) {
        $view_mode_options[$key] = $view_mode['label'];
      }
      // The View Mode drop-down is visible conditional on "#rendered_entity"
      // being selected in the Description drop-down above.
      $form['view_mode'] = array(
        '#type' => 'select',
        '#title' => $this->t('View mode'),
        '#description' => $this->t('View modes are ways of displaying entities.'),
        '#options' => $view_mode_options,
        '#default_value' => !empty($this->options['view_mode']) ? $this->options['view_mode'] : 'full',
        '#states' => array(
          'visible' => array(
            ':input[name="style_options[description_field]"]' => array(
              'value' => '#rendered_entity',
            ),
          ),
        ),
      );
    }

    $form = $form + $this->generateGmapSettingsForm($form, $form_state, $this->options, $default_settings);
  }

  /**
   * Validates the options form.
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

  }

  /**
   * Renders the View.
   */
  public function render() {
    $data = array();
    $geofield_name = $this->options['data_source'];
    if ($this->options['data_source']) {
      $this->renderFields($this->view->result);
      /* @var \Drupal\views\ResultRow  $result */
      foreach ($this->view->result as $id => $result) {

        $geofield_value = $this->getFieldValue($id, $geofield_name);

        if (empty($geofield_value)) {
          // In case the result is not among the raw results, get it from the
          // rendered results.
          $geofield_value = $this->rendered_fields[$id][$geofield_name];
        }
        if (!empty($geofield_value)) {
          $points = leaflet_process_geofield($geofield_value);

          // Render the entity with the selected view mode.
          if ($this->options['description_field'] === '#rendered_entity' && is_object($result)) {
            $entity = $this->entityManager->getStorage($this->entityType)->load($result->nid);
            $build = $this->entityManager->getViewBuilder($entity->getEntityTypeId())->view($entity, $this->options['view_mode'], $entity->language());
            $description = $this->renderer->render($build);
          }
          // Normal rendering via fields.
          elseif ($this->options['description_field']) {
            $description = $this->rendered_fields[$id][$this->options['description_field']];
          }

          // Attach pop-ups if we have a description field.
          if (isset($description)) {
            foreach ($points as &$point) {
              $point['popup'] = $description;
            }
          }

          // Attach also titles, they might be used later on.
          if ($this->options['name_field']) {
            foreach ($points as &$point) {
              $point['label'] = $this->rendered_fields[$id][$this->options['name_field']];
            }
          }

          $data = array_merge($data, $points);

          if (!empty($this->options['icon']) && $this->options['icon']['iconUrl']) {
            foreach ($data as $key => $feature) {
              $data[$key]['icon'] = $this->options['icon'];
            }
          }
        }
      }
    }

    // Always render the map, even if we do not have any data.
    $map = leaflet_map_get_info($this->options['map']);
    return leaflet_render_map($map, $data, $this->options['height'] . 'px');
  }

  /**
   * Set default options.
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['data_source'] = array('default' => '');
    $options['name_field'] = array('default' => '');
    $options['description_field'] = array('default' => '');
    $options['view_mode'] = array('default' => 'full');

    $geofieldGoogleMapDefaultSettings = [];
    foreach (self::getDefaultSettings() as $k => $setting) {
      $geofieldGoogleMapDefaultSettings[$k] = ['default' => $setting];
    }

    return $options + $geofieldGoogleMapDefaultSettings;
  }

}
