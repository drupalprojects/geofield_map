<?php

namespace Drupal\geofield_map\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

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
class GeofieldGoogleMapFormatter extends FormatterBase {

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

    /* @var array $elements */
    $elements = $this->generateSettingsFormElements();

    return $elements + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {

    $map_google_info = [
      '#markup' => $this->t('Google Map Info: @state', array('@state' => $this->getSetting('map_google_info'))),
    ];
    $map_google_apy_key = [
      '#markup' => $this->t('Google Maps API Key: @state', array('@state' => $this->getSetting('map_google_api_key') ? $this->getSetting('map_google_api_key') : t('<span style="color: red">Missing</span>'))),
    ];

    $map_width = [
      '#markup' => $this->t('Map width: @state', array('@state' => $this->getSetting('map_width'))),
    ];
    $map_height = [
      '#markup' => $this->t('Map height: @state', array('@state' => $this->getSetting('map_height'))),
    ];
    $map_zoom = [
      '#markup' => $this->t('Map zoom: @state', array('@state' => $this->getSetting('map_zoom'))),
    ];
    $map_min_zoom = [
      '#markup' => $this->t('Min Map zoom: @state', array('@state' => $this->getSetting('map_min_zoom'))),
    ];
    $map_max_zoom = [
      '#markup' => $this->t('Max Map zoom: @state', array('@state' => $this->getSetting('map_max_zoom'))),
    ];
    $map_max_zoom = [
      '#markup' => $this->t('Max Map zoom: @state', array('@state' => $this->getSetting('map_max_zoom'))),
    ];
    $map_center_settings = $this->getSetting('map_center');
    $map_center = [
      '#markup' => $this->t('Map Default Center: @state_lat, @state_lon', array('@state_lat' => $map_center_settings['lat'], '@state_lon' => $map_center_settings['lon'])),
    ];
    $map_streetview_show = [
      '#markup' => $this->t('Streetview show: @state', array('@state' => $this->getSetting('map_streetview_show') ? 'Yes' : 'No')),
    ];
    $other_settings = [
      '#markup' => $this->t('and other settings ...'),
    ];

    $summary = [
      'map_google_info' => $map_google_info,
      'map_google_api_key' => $map_google_apy_key,
      'map_width' => $map_width,
      'map_height' => $map_height,
      'map_zoom' => $map_zoom,
      'map_min_zoom' => $map_min_zoom,
      'map_max_zoom' => $map_max_zoom,
      'map_center' => $map_center,
      'map_streetview_show' => $map_streetview_show,
      'other_settings' => $other_settings,
    ];

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $settings = $this->getSettings();

    $map = [
      'label' => 'Geofield Google Map',
      'description' => t('Default Geofield Google Map.'),
      'settings' => [
        'map_google_api_key' => isset($settings['map_google_api_key']) ? $settings['map_google_api_key'] : NULL,
        'map_width' => isset($settings['map_width']) ? $settings['map_width'] : '100%',
        'map_height' => isset($settings['map_height']) ? $settings['map_height'] : '300px',
        'map_zoom' => isset($settings['map_zoom']) ? $settings['map_zoom'] : NULL,
        'map_min_zoom' => isset($settings['map_min_zoom']) ? $settings['map_min_zoom'] : '0',
        'map_max_zoom' => isset($settings['map_max_zoom']) ? $settings['map_max_zoom'] : '22',
      ],
    ];

    $elements = [];
    /* @var  \Drupal\geofield\Plugin\Field\FieldType\GeofieldItem $item */
    foreach ($items as $delta => $item) {
      $elements[$delta] = ['#markup' => $this->viewValue($item)];

      $features = geofield_map_process_geofield($this->viewValue($item));

      $elements[$delta] = geofield_map_googlemap_render($map, $features);
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
   * @return array
   *   The generated form elements array.
   */
  protected function generateSettingsFormElements($element = array()) {

    $settings = $this->getSettings();
    $zooms_range = range($this->getSetting('map_min_zoom'), '22');

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
      '#default_value' => $settings['map_scale'],
      '#return_value' => 1,
    );
    $element['map_overview'] = array(
      '#type' => 'checkbox',
      '#title' => t('Overview map'),
      '#description' => t('Show overview map'),
      '#default_value' => $settings['map_overview'],
      '#return_value' => 1,
    );

    $element['map_overview_opened'] = array(
      '#type' => 'checkbox',
      '#title' => t('Overview map state'),
      '#description' => t('Show overview map as open by default'),
      '#default_value' => $settings['map_overview_opened'],
      '#return_value' => 1,
    );
    $element['map_scrollwheel'] = array(
      '#type' => 'checkbox',
      '#title' => t('Scrollwheel'),
      '#description' => t('Enable scrollwheel zooming'),
      '#default_value' => $settings['map_scrollwheel'],
      '#return_value' => 1,
    );
    $element['map_draggable'] = array(
      '#type' => 'checkbox',
      '#title' => t('Draggable'),
      '#description' => t('Enable dragging on the map'),
      '#default_value' => $settings['map_draggable'],
      '#return_value' => 1,
    );
    $element['map_streetview_show'] = array(
      '#type' => 'checkbox',
      '#title' => t('Show streetview button'),
      '#default_value' => $settings['map_streetview_show'],
      '#return_value' => 1,
    );

    return $element;

  }

}
