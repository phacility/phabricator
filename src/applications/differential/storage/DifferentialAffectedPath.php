<?php

/**
 * Denormalized index table which stores relationships between revisions in
 * Differential and paths in Diffusion.
 */
final class DifferentialAffectedPath extends DifferentialDAO {

  protected $repositoryID;
  protected $pathID;
  protected $revisionID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'id' => null,
        'repositoryID' => 'id?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'PRIMARY' => null,
        'revisionID' => array(
          'columns' => array('revisionID'),
        ),
        'key_path' => array(
          'columns' => array('pathID', 'repositoryID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
