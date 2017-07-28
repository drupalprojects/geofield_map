<?php

namespace Drupal\geofield_map\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\node\NodeStorageInterface;

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

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Node storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs an GeofieldGoogleMapFormatter object.
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
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity type manager.
   * @param \Drupal\node\NodeStorageInterface $node_storage
   *   The node storage.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // @see \Drupal\Core\Field\FormatterPluginManager::createInstance().
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'map_google_api_key' => '',
      'map_width' => '100%',
      'map_height' => '300px',
      'map_zoom' => '8',
      'map_min_zoom' => '0',
      'map_max_zoom' => '22',
      'map_controltype' => 'default',
      'map_mtc' => 'standard',
      'map_pancontrol' => 1,
      'map_maptype' => 'map',
      'map_baselayers_map' => 1,
      'map_baselayers_satellite' => 1,
      'map_baselayers_hybrid' => 1,
      'map_baselayers_physical' => 0,
      'map_scale' => 0,
      'map_overview' => 0,
      'map_overview_opened' => 0,
      'map_scrollwheel' => 0,
      'map_draggable' => 0,
      'map_streetview_show' => 0,
      'map_center' => array(
        'lat' => 0,
        'lon' => 0,
      ),
        // Implement default settings.
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    /* @var array $element */
    $element = $this->generateSettingsFormElements();

    return $element + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    // Implement settings summary.

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = ['#markup' => $this->viewValue($item)];
    }

    return $elements;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item) {
    // The text value has no text format assigned to it, so the user input
    // should equal the output, including newlines.
    return nl2br(Html::escape($item->value));
  }

  /**
   * Generate the map settings form elements.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function generateSettingsFormElements($element = array()) {

    $territorial_report_fields = $this->entityFieldManager->getFieldDefinitions('node', 'geoplace');
    $geofield_definition = $territorial_report_fields['field_geofield'];

    $settings = $this->getSettings();

    $zooms_range = range($this->getSetting('map_min_zoom'), '22');

    $fieldSettings = $this->getFieldSettings();

    $element['map_width'] = array(
      '#type' => 'textfield',
      '#title' => t('Map width'),
      '#default_value' => $settings['map_width'],
      '#size' => 25,
      '#maxlength' => 25,
      '#description' => t('The default width of a Google map, as a CSS length or percentage. Examples: <em>50px</em>, <em>5em</em>, <em>2.5in</em>, <em>95%</em>'),
      '#required' => TRUE,
    );
    $element['map_height'] = array(
      '#type' => 'textfield',
      '#title' => t('Map height'),
      '#default_value' => $settings['map_height'],
      '#size' => 25,
      '#maxlength' => 25,
      '#description' => t('The default height of a Google map, as a CSS length or percentage. Examples: <em>50px</em>, <em>5em</em>, <em>2.5in</em>, <em>95%</em>'),
      '#required' => TRUE,
    );
    $element['map_center'] = array(
      '#type' => 'geofield_latlon',
      '#title' => t('Default Center'),
      '#default_value' => $settings['map_center'],
      '#size' => 25,
      '#description' => t('If there are no entries on the map, where should the map be centered?'),
      '#geolocation' => TRUE,
    );
    $element['map_zoom'] = array(
      '#type' => 'select',
      '#title' => t('Zoom'),
      '#default_value' => isset($settings['map_zoom']) ? $settings['map_zoom'] : min(8, $settings['map_max_zoom']),
      '#options' => array_combine($zooms_range, $zooms_range),
      '#description' => t('The default zoom level of a Google map.'),
    );
    $element['map_min_zoom'] = array(
      '#type' => 'select',
      '#title' => t('Zoom minimum'),
      '#default_value' => isset($settings['map_min_zoom']) ? $settings['map_min_zoom'] : 0,
      '#options' => array_combine($zooms_range, $zooms_range),
      '#description' => t('The minimum zoom level of a Google map.'),
    );
    $element['map_max_zoom'] = array(
      '#type' => 'select',
      '#title' => t('Zoom maximum'),
      '#default_value' => isset($settings['map_max_zoom']) ? $settings['map_max_zoom'] : 0,
      '#options' => array_combine($zooms_range, $zooms_range),
      '#description' => t('The maximum zoom level of a Google map. Set to 0 to ignore limit.'),
      '#element_validate' => array('map_zoom_level_validate'),
    );
    $element['map_controltype'] = array(
      '#type' => 'select',
      '#title' => t('Zoom Control Type'),
      '#options' => array(
        'none' => t('None'),
        'default' => t('Default'),
        'small' => t('Small'),
        'large' => t('Large'),
      ),
      '#default_value' => $settings['map_controltype'],
    );
    $element['map_mtc'] = array(
      '#type' => 'select',
      '#title' => t('Map Control Type'),
      '#options' => array(
        'none' => t('None'),
        'standard' => t('Horizontal bar'),
        'menu' => t('Dropdown'),
      ),
      '#default_value' => $settings['map_mtc'],
    );
    $element['map_pancontrol'] = array(
      '#type' => 'checkbox',
      '#title' => t('Show Pan control'),
      '#default_value' => $settings['map_pancontrol'],
      '#return_value' => 1,
    );

    $mapopts = array('map' => t('Standard street map'));
    if ($settings['map_baselayers_satellite']) {
      $mapopts['satellite'] = t('Standard satellite map');
    }
    if ($settings['map_baselayers_hybrid']) {
      $mapopts['hybrid'] = t('Hybrid satellite map');
    }
    if ($settings['map_baselayers_physical']) {
      $mapopts['physical'] = t('Terrain map');
    }


    $element['map_maptype'] = array(
      '#type' => 'select',
      '#title' => t('Default Map Type'),
      '#default_value' => $settings['map_maptype'],
      '#options' => array(
        'map' => t('Standard street map'),
        'satellite' => t('Standard satellite map'),
        'hybrid' => t('Hybrid satellite map'),
        'physical' => t('Terrain map'),
      ),
    );
    $element['map_baselayers_map'] = array(
      '#type' => 'checkbox',
      '#title' => t('Standard street map'),
      '#description' => t('The standard default street map.'),
      '#default_value' => $settings['map_baselayers_map'],
      '#return_value' => 1,
      '#prefix' => '<fieldset><legend>' . t('Enable map types') . '</legend>',
    );
    $element['map_baselayers_satellite'] = array(
      '#type' => 'checkbox',
      '#title' => t('Standard satellite map'),
      '#description' => t('Satellite view without street overlay.'),
      '#default_value' => $settings['map_baselayers_satellite'],
      '#return_value' => 1,
    );
    $element['map_baselayers_hybrid'] = array(
      '#type' => 'checkbox',
      '#title' => t('Hybrid satellite map'),
      '#description' => t('Satellite view with street overlay.'),
      '#default_value' => $settings['map_baselayers_hybrid'],
      '#return_value' => 1,
    );
    $element['map_baselayers_physical'] = array(
      '#type' => 'checkbox',
      '#title' => t('Terrain map'),
      '#description' => t('Map with physical data (terrain, vegetation.)'),
      '#default_value' => $settings['map_baselayers_physical'],
      '#return_value' => 1,
      '#suffix' => '</fieldset>',
    );
    $element['map_scale'] = array(
      '#type' => 'checkbox',
      '#title' => t('Scale'),
      '#description' => t('Show scale'),
      '#default_value' =>  $settings['map_scale'],
      '#return_value' => 1,
    );
    $element['map_overview'] = array(
      '#type' => 'checkbox',
      '#title' => t('Overview map'),
      '#description' => t('Show overview map'),
      '#default_value' =>  $settings['map_overview'],
      '#return_value' => 1,
    );

    $element['map_overview_opened'] = array(
      '#type' => 'checkbox',
      '#title' => t('Overview map state'),
      '#description' => t('Show overview map as open by default'),
      '#default_value' =>  $settings['map_overview_opened'],
      '#return_value' => 1,
    );
    $element['map_scrollwheel'] = array(
      '#type' => 'checkbox',
      '#title' => t('Scrollwheel'),
      '#description' => t('Enable scrollwheel zooming'),
      '#default_value' =>  $settings['map_scrollwheel'],
      '#return_value' => 1,
    );
    $element['map_draggable'] = array(
      '#type' => 'checkbox',
      '#title' => t('Draggable'),
      '#description' => t('Enable dragging on the map'),
      '#default_value' =>  $settings['map_draggable'],
      '#return_value' => 1,
    );
    $element['map_streetview_show'] = array(
      '#type' => 'checkbox',
      '#title' => t('Show streetview button'),
      '#default_value' =>  $settings['map_streetview_show'],
      '#return_value' => 1,
    );

    return $element;

  }

}
