<?php

final class NuanceRequestorSource
  extends NuanceDAO {

  protected $requestorPHID;
  protected $sourcePHID;
  protected $data;

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'data' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

}
