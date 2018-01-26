<?php

final class PhabricatorEpochExportField
  extends PhabricatorExportField {

  private $zone;

  public function getTextValue($value) {
    if (!isset($this->zone)) {
      $this->zone = new DateTimeZone('UTC');
    }

    try {
      $date = new DateTime('@'.$value);
    } catch (Exception $ex) {
      return null;
    }

    $date->setTimezone($this->zone);
    return $date->format('c');
  }

  public function getNaturalValue($value) {
    return (int)$value;
  }

}
