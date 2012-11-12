<?php

final class PhabricatorRepositoryShortcut extends PhabricatorRepositoryDAO {

  protected $name;
  protected $href;
  protected $description;
  protected $sequence;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

}
