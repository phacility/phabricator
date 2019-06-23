<?php

final class DoorkeeperURIRef extends Phobject {

  private $uri;
  private $applicationType;
  private $applicationDomain;
  private $objectType;
  private $objectID;
  private $text;
  private $displayMode = self::DISPLAY_FULL;

  const DISPLAY_FULL = 'full';
  const DISPLAY_SHORT = 'short';

  public function setURI(PhutilURI $uri) {
    $this->uri = $uri;
    return $this;
  }

  public function getURI() {
    return $this->uri;
  }

  public function setApplicationType($application_type) {
    $this->applicationType = $application_type;
    return $this;
  }

  public function getApplicationType() {
    return $this->applicationType;
  }

  public function setApplicationDomain($application_domain) {
    $this->applicationDomain = $application_domain;
    return $this;
  }

  public function getApplicationDomain() {
    return $this->applicationDomain;
  }

  public function setObjectType($object_type) {
    $this->objectType = $object_type;
    return $this;
  }

  public function getObjectType() {
    return $this->objectType;
  }

  public function setObjectID($object_id) {
    $this->objectID = $object_id;
    return $this;
  }

  public function getObjectID() {
    return $this->objectID;
  }

  public function setText($text) {
    $this->text = $text;
    return $this;
  }

  public function getText() {
    return $this->text;
  }

  public function setDisplayMode($display_mode) {
    $options = array(
      self::DISPLAY_FULL => true,
      self::DISPLAY_SHORT => true,
    );

    if (!isset($options[$display_mode])) {
      throw new Exception(
        pht(
          'DoorkeeperURIRef display mode "%s" is unknown.',
          $display_mode));
    }

    $this->displayMode = $display_mode;
    return $this;
  }

  public function getDisplayMode() {
    return $this->displayMode;
  }

}
