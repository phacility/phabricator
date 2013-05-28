<?php

final class DivinerLiveAtom extends DivinerDAO {

  protected $symbolPHID;
  protected $content;
  protected $atomData;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'atomData' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

}
