<?php

/**
 * Denormalized index table which stores relationships between revisions in
 * Differential and paths in Diffusion.
 *
 * @group differential
 */
final class DifferentialAffectedPath extends DifferentialDAO {

  protected $repositoryID;
  protected $pathID;
  protected $epoch;
  protected $revisionID;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }
}
