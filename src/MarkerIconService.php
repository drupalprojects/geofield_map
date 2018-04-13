<?php

namespace Drupal\geofield_map;

use Drupal\Component\Utility\Bytes;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;

/**
 * Provides an Icon Managed File Service.
 */
class MarkerIconService {

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

  /**
   * The list of file upload validators.
   *
   * @var array
   */
  protected $fileUploadValidators;

  /**
   * The geofield map settings.
   *
   * @var array
   */
  protected $geofieldMapSettings;

  /**
   * Constructor of the Icon Managed File Service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   A config factory for retrieving required config objects.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TranslationInterface $string_translation,
    EntityTypeManagerInterface $entity_manager
  ) {
    $this->stringTranslation = $string_translation;
    $this->entityManager = $entity_manager;
    $this->geofieldMapSettings = $config_factory->get('geofield_map.settings');
    $this->fileUploadValidators = [
      'file_validate_extensions' => [$this->geofieldMapSettings->get('theming.markers_extensions')],
      'file_validate_is_image' => [],
      'file_validate_size' => [Bytes::toInt($this->geofieldMapSettings->get('theming.markers_filesize'))],
    ];
  }

  /**
   * Validates the Icon Image statuses.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validateIconImageStatus(array $element, FormStateInterface $form_state) {
    $clicked_button = end($form_state->getTriggeringElement()['#parents']);
    if (!empty($element['#value']['fids'][0])) {
      $file = File::load($element['#value']['fids'][0]);
      if (in_array($clicked_button, ['save_settings', 'submit'])) {
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
   * Generate Icon File Managed Element.
   *
   * @param int $fid
   *   The file to save.
   *
   * @return array
   *   The icon preview element.
   */
  public function getIconFileManagedElement($fid) {

    $upload_location = $this->geofieldMapSettings->get('theming.markers_location.security') . $this->geofieldMapSettings->get('theming.markers_location.rel_path');

    $element = [
      '#type' => 'managed_file',
      '#title' => t('Choose a Marker Icon file'),
      '#title_display' => 'invisible',
      '#default_value' => !empty($fid) ? [$fid] : NULL,
      '#multiple' => FALSE,
      '#error_no_message' => FALSE,
      '#upload_location' => $upload_location,
      '#upload_validators' => $this->fileUploadValidators,
      '#progress_message' => $this->t('Please wait...'),
      '#progress_indicator' => 'throbber',
      '#element_validate' => [
        [get_class($this), 'validateIconImageStatus'],
      ],
    ];

    if (!empty($fid)) {
      $element['preview'] = $this->getIconThumbnail($fid);
    }

    return $element;
  }

  /**
   * Generate File Upload Help Message.
   *
   * @return array
   *   The field upload help element.
   */
  public function getFileUploadHelp() {
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
   * Generate Icon Thumbnail.
   *
   * @param int $fid
   *   The file identifier.
   *
   * @return array
   *   The icon view render array..
   */
  public function getIconThumbnail($fid) {
    $icon_view_element = [];
    try {
      /* @var \Drupal\file\Entity\file $file */
      $file = $this->entityManager->getStorage('file')->load($fid);
      if ($file instanceof FileInterface) {
        $icon_view_element = [
          '#weight' => -10,
          '#theme' => 'image_style',
          '#width' => '40px',
          '#height' => '40px',
          '#style_name' => 'thumbnail',
          '#uri' => $file->getFileUri(),
        ];;
      }
    }
    catch (InvalidPluginDefinitionException $e) {
    }

    return $icon_view_element;
  }

  /**
   * Generate File Managed Url from fid.
   *
   * @param int $fid
   *   The file identifier.
   *
   * @return string
   *   The icon preview element.
   */
  public function getFileManagedUrl($fid = NULL) {
    if (isset($fid) && $file = File::load($fid)) {
      $uri = $file->getFileUri();
      $url = file_create_url($uri);
      return $url;
    }
    return NULL;
  }

}
