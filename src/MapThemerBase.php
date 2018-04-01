<?php

namespace Drupal\geofield_map;

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;

/**
 * A base class for MapThemer plugins.
 */
abstract class MapThemerBase extends PluginBase implements MapThemerInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $translationManager;

  /**
   * The Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityManager;

  protected $fileUploadValidators;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    TranslationInterface $translation_manager,
    EntityTypeManagerInterface $entity_manager

  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->config = $config_factory;
    $this->setStringTranslation($translation_manager);
    $this->entityManager = $entity_manager;
    $this->fileUploadValidators = [
      'file_validate_extensions' => ['gif png jpg jpeg svg'],
      'file_validate_is_image' => [],
      'file_validate_size' => [Bytes::toInt('250 KB')],
    ];
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultSettings($k = NULL) {
    $default_settings = $this->pluginDefinition['defaultSettings'];
    if (!empty($k)) {
      return $default_settings[$k];
    }
    return $default_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultThemerElement(array $defaults, FormStateInterface $form_state) {
    $default_value = !empty($defaults['map_marker_and_infowindow']['theming'][$this->pluginId]['values']) ? $defaults['map_marker_and_infowindow']['theming'][$this->pluginId]['values'] : $this->defaultSettings('values');
    return $default_value;
  }

  /**
   * Validates the Icon Image statuses.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validateIconImageStatuses(array $element, FormStateInterface $form_state) {
    $clicked_button = end($form_state->getTriggeringElement()['#parents']);
    if (!empty($element['#value']['fids'][0])) {
      $file = File::load($element['#value']['fids'][0]);
      if ($clicked_button == 'submit') {
        $file->setPermanent();
        self::fileSave($file);
      }
      if ($clicked_button == 'remove_button') {
        $file->setTemporary();
        self::fileSave($file);
      }
    }
  }

  /**
   * Save a file, handling exception.
   *
   * @param \Drupal\file\Entity\file $file
   *   The file to save.
   */
  public static function fileSave(file $file) {
    try {
      $file->save();
    }
    catch (EntityStorageException $e) {
      \Drupal::logger('Geofield Map Themer')->log('warning', t("The file couldn't be saved: @message", [
        '@message' => $e->getMessage(),
      ])
      );
    }
  }

  /**
   * Generate File Icon preview.
   *
   * @param int $fid
   *   The file to save.
   *
   * @return array
   *   The icon preview element.
   */
  protected function getFileIconElement($fid) {

    $element = [
      '#type' => 'managed_file',
      '#title' => t('Choose a Marker Icon file'),
      '#title_display' => 'invisible',
      '#default_value' => !empty($fid) ? [$fid] : NULL,
      '#multiple' => FALSE,
      '#error_no_message' => FALSE,
      '#upload_location' => 'public://geofieldmap_markers',
      '#upload_validators' => $this->fileUploadValidators,
      '#progress_message' => $this->t('Please wait...'),
      '#progress_indicator' => 'throbber',
      '#element_validate' => [
        [get_class($this), 'validateIconImageStatuses'],
      ],
    ];

    if (!empty($fid)) {
      try {
        /* @var \Drupal\file\Entity\file $file */
        $file = $this->entityManager->getStorage('file')->load($fid);
        $element['preview'] = $this->getFileIconPreview($file);
      }
      catch (InvalidPluginDefinitionException $e) {
        $element['preview'] = [];
      }
    }

    return $element;
  }

  /**
   * Generate File Upload Help Message.
   *
   * @return array
   *   The fiel upload help element.
   */
  protected function getFileUploadHelp() {
    return [
      '#type' => 'container',
      '#tag' => 'div',
      'file_upload_help' => [
        '#theme' => 'file_upload_help',
        '#upload_validators' => $this->fileUploadValidators,
        '#cardinality' => 1,
      ],
      '#attributes' => [
        'style' => ['style' => 'font-size:0.8em; color: gray; text-transform: lowercase; font-weight: normal'],
      ],
    ];
  }

  /**
   * Generate File Icon preview.
   *
   * @param \Drupal\file\Entity\file $file
   *   The file to save.
   *
   * @return array
   *   The icon preview element.
   */
  protected function getFileIconPreview(file $file) {
    return [
      '#weight' => -10,
      '#theme' => 'image_style',
      '#width' => '40px',
      '#height' => '40px',
      '#style_name' => 'thumbnail',
      '#uri' => $file->getFileUri(),
    ];
  }

}
