<?php

final class PhabricatorOwnersPath extends PhabricatorOwnersDAO {

  protected $packageID;
  protected $repositoryPHID;
  protected $path;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

}
