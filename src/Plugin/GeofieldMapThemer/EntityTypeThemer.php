<?php

namespace Drupal\geofield_map\Plugin\GeofieldMapThemer;

use Drupal\geofield_map\MapThemerBase;
use Drupal\Core\Form\FormStateInterface;

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
 * )
 */
class EntityTypeThemer extends MapThemerBase {

  /**
   * {@inheritdoc}
   */
  public function buildMapThemerForm(array &$form, FormStateInterface $form_state) {

  }

}
