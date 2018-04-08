<?php

namespace Drupal\geofield_map\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\geofield_map\MapThemerPluginManager;
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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    AccountInterface $current_user,
    LinkGeneratorInterface $link_generator,
    RendererInterface $renderer,
    MapThemerPluginManager $map_themer_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config_factory;
    $this->currentUser = $current_user;
    $this->link = $link_generator;
    $this->renderer = $renderer;
    $this->mapThemerManager = $map_themer_manager;
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
      $container->get('plugin.manager.geofield_map.themer')
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

    if (!empty($geofield_legends)) {

      $form['geofield_map_legend'] = [
        '#type' => 'select',
        '#title' => $this->t('Geofield Map Legend'),
        '#description' => $this->t('Select the Geofield Map legend to render in this block<br>Choose the View and the Display you want to grab the Legend definition from.'),
        '#options' => $geofield_legends,
        '#default_value' => isset($this->configuration['geofield_map_legend']) ? $this->configuration['geofield_map_legend'] : t('none'),
        '#required' => TRUE,
      ];
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
      $view_displays = $this->config->get('views.view.' . $view_id)
        ->get('display');
      if (!empty($view_displays) && !empty($view_displays[$view_display_id])) {
        $view_options = $view_displays[$view_display_id]['display_options']['style']['options'];
        $plugin_id = isset($view_options['map_marker_and_infowindow']['theming']) ? $view_options['map_marker_and_infowindow']['theming']['plugin_id'] : NULL;
        if (isset($plugin_id) && $plugin_id != 'none') {
          try {
            $this->mapThemerPlugin = $this->mapThemerManager->createInstance($plugin_id);
            $legend = [
              '#markup' => $this->t('This legend will be rendered by the @plugin_id plugin.', [
                '@plugin_id' => $this->mapThemerPlugin->getName(),
              ]),
            ];
          }
          catch (PluginException $e) {
            $legend = [
              '#markup' => $this->t("This legend won't be rendered due to @error_message", [
                '@error_message' => $e->getMessage(),
              ]),
            ];
          }
        }
      }
      elseif ($this->currentUser->hasPermission('configure geofield_map')) {
        $legend = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t("The chosen Geofield Map Legend can't be rendered because the chosen @view_id:@view_display_id view & display combination don't exists anymore. <u>Please reconfigure this Geofield Map Legend block consequently.</u>", [
            '@view_id' => $view_id,
            '@view_display_id' => $view_display_id,
          ]),
          '#attributes' => [
            'class' => ['geofield-map-message'],
          ],
        ];
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
  }

}
