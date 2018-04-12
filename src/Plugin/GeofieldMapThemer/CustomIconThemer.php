<?php

namespace Drupal\geofield_map\Plugin\GeofieldMapThemer;

use Drupal\geofield_map\MapThemerBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield_map\Plugin\views\style\GeofieldGoogleMapViewStyle;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\Markup;

/**
 * Style plugin to render a View output as a Leaflet map.
 *
 * @ingroup geofield_map_themers_plugins
 *
 * Attributes set below end up in the $this->definition[] array.
 *
 * @MapThemer(
 *   id = "geofieldmap_custom_icon",
 *   name = @Translation("Geofield Map Custom Icon Image"),
 *   description = "This Geofield Map Themer allows the definition of a unique custom Marker Icon, valid for all the Map Markers.",
 *   type = "single_value",
 *   defaultSettings = {
 *    "values" = NULL
 *   },
 * )
 */
class CustomIconThemer extends MapThemerBase {

  /**
   * {@inheritdoc}
   */
  public function buildMapThemerElement(array $defaults, array &$form, FormStateInterface $form_state, GeofieldGoogleMapViewStyle $geofieldMapView) {

    // Get the existing (Default) Element settings.
    $default_element = $this->getDefaultThemerElement($defaults, $form_state);

    $fid = (integer) !empty($default_element['icon_file']['fids']) ? $default_element['icon_file']['fids'] : NULL;
    $element = [
      '#type' => 'container',
      'description' => [
        '#markup' => Markup::create($this->t('The chosen icon file will be used as Marker for all Geofield Map features @file_upload_help', [
          '@file_upload_help' => $this->renderer->renderPlain($this->iconFile->getFileUploadHelp()),
        ])),
      ],
      'icon_file' => $this->iconFile->getIconFileManagedElement($fid[0]),
    ];

    return $element;

  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(array $datum, GeofieldGoogleMapViewStyle $geofieldMapView, EntityInterface $entity, $map_theming_values) {
    // The Custom Icon Themer plugin defines a unique icon value.
    if (!empty($map_theming_values['icon_file']['fids'])) {
      return $this->iconFile->getFileManagedUrl($map_theming_values['icon_file']['fids'][0]);
    }
    return NULL;
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
        'class' => ['geofield-map-legend', 'custom-icon'],
      ],
    ];

    $fid = (integer) !empty($map_theming_values['icon_file']['fids']) ? $map_theming_values['icon_file']['fids'][0] : NULL;
    $legend['custom-icon'] = [
      'value' => [
        '#type' => 'container',
        'label' => [
          '#markup' => $this->t('All Markers'),
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

    return $legend;
  }

}
