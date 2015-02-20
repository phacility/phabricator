<?php

/**
 * Denormalized index table which stores relationships between revisions in
 * Differential and paths in Diffusion.
 */
final class DifferentialAffectedPath extends DifferentialDAO {

  protected $repositoryID;
  protected $pathID;
  protected $epoch;
  protected $revisionID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'id' => null,
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'PRIMARY' => null,
        'repositoryID' => array(
          'columns' => array('repositoryID', 'pathID', 'epoch'),
        ),
        'revisionID' => array(
          'columns' => array('revisionID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
