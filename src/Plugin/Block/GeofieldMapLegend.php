<?php

namespace Drupal\geofield_map\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\geofield_map\MapThemerPluginManager;
use Drupal\geofield_map\MarkerIconService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Views;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Component\Plugin\Exception\PluginException;

/**
 * Provides a custom Geofield Map Legend block.
 *
 * @Block(
 *   id = "geofield_map_legend",
 *   admin_label = @Translation("Geofield Map Legend"),
 *   category = @Translation("Geofield Map")
 * )
 */
class GeofieldMapLegend extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The Current User.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The Link generator Service.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $link;


  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The MapThemer Manager service .
   *
   * @var \Drupal\geofield_map\MapThemerPluginManager
   */
  protected $mapThemerManager;

  /**
   * The MapThemer Manager service .
   *
   * @var \Drupal\geofield_map\MapThemerInterface
   */
  protected $mapThemerPlugin;

  /**
   * The Icon Managed File Service.
   *
   * @var \Drupal\geofield_map\MarkerIconService
   */
  protected $markerIcon;

  /**
   * Creates a LocalActionsBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The Current User.
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The Link Generator service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Renderer service.
   * @param \Drupal\geofield_map\MapThemerPluginManager $map_themer_manager
   *   The mapThemerManager service.
   * @param \Drupal\geofield_map\MarkerIconService $marker_icon_service
   *   The Marker Icon Service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    AccountInterface $current_user,
    LinkGeneratorInterface $link_generator,
    RendererInterface $renderer,
    MapThemerPluginManager $map_themer_manager,
    MarkerIconService $marker_icon_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config_factory;
    $this->currentUser = $current_user;
    $this->link = $link_generator;
    $this->renderer = $renderer;
    $this->mapThemerManager = $map_themer_manager;
    $this->markerIcon = $marker_icon_service;
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
      $container->get('current_user'),
      $container->get('link_generator'),
      $container->get('renderer'),
      $container->get('plugin.manager.geofield_map.themer'),
      $container->get('geofield_map.marker_icon')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    // Attach Geofield Map Libraries.
    $form['#attached']['library'][] = 'geofield_map/geofield_map_general';
    $form['#attached']['library'][] = 'geofield_map/geofield_map_legend';

    $geofield_map_legends = $this->getGeofieldMapLegends();

    if (!empty($geofield_map_legends)) {
      $form['geofield_map_legend'] = [
        '#type' => 'select',
        '#title' => $this->t('Geofield Map Legend'),
        '#description' => $this->t('Select the Geofield Map legend to render in this block<br>Choose the View and the Display you want to grab the Legend definition from.'),
        '#options' => $geofield_map_legends,
        '#default_value' => isset($this->configuration['geofield_map_legend']) ? $this->configuration['geofield_map_legend'] : t('none'),
        '#required' => TRUE,
      ];

      $form['values_label'] = [
        '#title' => $this->t('Values Column Label'),
        '#type' => 'textfield',
        '#description' => $this->t('Set the Label text to be shown for the Values column. Empty for any Label.'),
        '#default_value' => isset($this->configuration['values_label']) ? $this->configuration['values_label'] : $this->t('Value'),
        '#size' => 26,
      ];

      $form['markers_label'] = [
        '#title' => $this->t('Markers Column Label'),
        '#type' => 'textfield',
        '#description' => $this->t('Set the Label text to be shown for the Markers/Icon column. Empty for any Label.'),
        '#default_value' => isset($this->configuration['markers_label']) ? $this->configuration['markers_label'] : $this->t('Marker/Icon'),
        '#size' => 26,
      ];

      // Define the list of possible legend icon image style.
      $markers_image_style_options = array_merge([
        '_map_theming_image_style_' => '<- Reflect the Map Theming Icon Image Styles ->',
      ], $this->markerIcon->getImageStyleOptions());

      // Force add the geofield_map_default_icon_style, if not existing.
      if (!in_array('geofield_map_default_icon_style', array_keys($markers_image_style_options))) {
        $markers_image_style_options['geofield_map_default_icon_style'] = 'geofield_map_default_icon_style';
      }

      $form['markers_image_style'] = [
        '#type' => 'select',
        '#title' => t('Markers Image style'),
        '#options' => $markers_image_style_options,
        '#default_value' => isset($this->configuration['markers_image_style']) ? $this->configuration['markers_image_style'] : 'geofield_map_default_icon_style',
        '#description' => $this->t('Choose the image style the markers icons will be rendered in the Legend with.'),
      ];

      $form['legend_notes'] = array(
        '#type' => 'textarea',
        '#title' => $this->t('Legend Notes'),
        '#description' => $this->t('Write here notes to the Legend that will be rendered as Notes (caption or footer depending on the specific Geofield Map Themer plugin Legend rendering).'),
        '#default_value' => isset($this->configuration['legend_notes']) ? $this->configuration['legend_notes'] : '',
        '#rows' => 3,
      );
    }
    else {
      $form['geofield_map_legend_warning'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('No eligible Geofield Map View Style and Theming have been defined/found.<br>Please @define_view_link with Geofield Map View Style and (not null) Theming to be able to choose at least a Legend to render.', [
          '@define_view_link' => $this->link->generate($this->t('define one View'), Url::fromRoute('entity.view.collection')),
        ]),
        '#attributes' => [
          'class' => ['geofield-map-warning'],
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    // Attach Geofield Map Libraries.
    $form['#attached']['library'][] = 'geofield_map/geofield_map_general';

    $legend = [];
    $build = [
      '#type' => 'container',
      '#attached' => [
        'library' => ['geofield_map/geofield_map_general'],
      ],
    ];
    $geofield_map_legend_id = $this->configuration['geofield_map_legend'];
    if (!empty($geofield_map_legend_id)) {
      list($view_id, $view_display_id) = explode(':', $geofield_map_legend_id);
      $legend_failure = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t("The Legend can't be rendered because the chosen [@view_id:@view_display_id] view & view display combination don't exists or correspond to a valid Geofield Map Legend anymore. <u>Please reconfigure this Geofield Map Legend block consequently.</u>", [
          '@view_id' => $view_id,
          '@view_display_id' => $view_display_id,
        ]),
        '#attributes' => [
          'class' => ['geofield-map-message legend-failure-message'],
        ],
      ];
      $view_displays = $this->config->get('views.view.' . $view_id)->get('display');
      if (!empty($view_displays) && !empty($view_displays[$view_display_id])) {
        $view_options = $view_displays[$view_display_id]['display_options']['style']['options'];
        $plugin_id = isset($view_options['map_marker_and_infowindow']['theming']) ? $view_options['map_marker_and_infowindow']['theming']['plugin_id'] : NULL;
        if (isset($plugin_id) && $plugin_id != 'none' && isset($view_options['map_marker_and_infowindow']['theming'][$plugin_id])) {
          try {
            $this->mapThemerPlugin = $this->mapThemerManager->createInstance($plugin_id);
            $theming_values = $view_options['map_marker_and_infowindow']['theming'][$plugin_id]['values'];
            $legend = $this->mapThemerPlugin->getLegend($theming_values, $this->configuration);
          }
          catch (PluginException $e) {
            if ($this->currentUser->hasPermission('configure geofield_map')) {
              $legend = [
                '#markup' => $this->t("This legend is not being rendered as @error_message", [
                  '@error_message' => $e->getMessage(),
                ]),
              ];
            }
          }
        }
        elseif ($this->currentUser->hasPermission('configure geofield_map')) {
          $legend = $legend_failure;
        }
      }
      elseif ($this->currentUser->hasPermission('configure geofield_map')) {
        $legend = $legend_failure;
      }
    }

    $build['legend'] = $legend;
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['geofield_map_legend'] = $form_state->getValue('geofield_map_legend');
    $this->configuration['legend_notes'] = $form_state->getValue('legend_notes');
    $this->configuration['values_label'] = $form_state->getValue('values_label');
    $this->configuration['markers_label'] = $form_state->getValue('markers_label');
    $this->configuration['markers_image_style'] = $form_state->getValue('markers_image_style');
  }

  /**
   * Get elegible Geofield Map legends.
   *
   * Find of Geofield Map Views Styles where a theming has
   * been defined and outputs them in the form of view_id:view_display_id array
   * list.
   *
   * @return array
   *   The legends list.
   */
  protected function getGeofieldMapLegends() {
    $geofield_legends = [];
    /* @var \Drupal\views\Entity\View $view */
    foreach ($enabled_views = Views::getEnabledViews() as $view_id => $view) {
      foreach ($this->config->get('views.view.' . $view_id)->get('display') as $id => $view_display) {
        if (isset($view_display['display_options']['style']) && $view_display['display_options']['style']['type'] == 'geofield_google_map') {
          $view_options = $view_display['display_options']['style']['options'];
          $plugin_id = isset($view_options['map_marker_and_infowindow']['theming']) ? $view_options['map_marker_and_infowindow']['theming']['plugin_id'] : NULL;
          if (isset($plugin_id) && $plugin_id != 'none') {
            $geofield_legends[$view_id . ':' . $id] = $view->label() . ' - display: ' . $id;
          }
        }
      }
    }
    return $geofield_legends;
  }

}
