<?php

final class DoorkeeperObjectRef extends Phobject {

  private $objectKey;
  private $applicationType;
  private $applicationDomain;
  private $objectType;
  private $objectID;
  private $attributes = array();
  private $isVisible;
  private $syncFailed;
  private $externalObject;

  public function newExternalObject() {
    return id(new DoorkeeperExternalObject())
      ->setApplicationType($this->getApplicationType())
      ->setApplicationDomain($this->getApplicationDomain())
      ->setObjectType($this->getObjectType())
      ->setObjectID($this->getObjectID())
      ->setViewPolicy(PhabricatorPolicies::POLICY_USER);
  }

  public function attachExternalObject(
    DoorkeeperExternalObject $external_object) {
    $this->externalObject = $external_object;
    return $this;
  }

  public function getExternalObject() {
    if (!$this->externalObject) {
      throw new PhutilInvalidStateException('attachExternalObject');
    }
    return $this->externalObject;
  }

  public function setIsVisible($is_visible) {
    $this->isVisible = $is_visible;
    return $this;
  }

  public function getIsVisible() {
    return $this->isVisible;
  }

  public function setSyncFailed($sync_failed) {
    $this->syncFailed = $sync_failed;
    return $this;
  }

  public function getSyncFailed() {
    return $this->syncFailed;
  }

  public function getAttribute($key, $default = null) {
    return idx($this->attributes, $key, $default);
  }

  public function setAttribute($key, $value) {
    $this->attributes[$key] = $value;
    return $this;
  }

  public function setObjectID($object_id) {
    $this->objectID = $object_id;
    return $this;
  }

  public function getObjectID() {
    return $this->objectID;
  }


  public function setObjectType($object_type) {
    $this->objectType = $object_type;
    return $this;
  }

  public function getObjectType() {
    return $this->objectType;
  }


  public function setApplicationDomain($application_domain) {
    $this->applicationDomain = $application_domain;
    return $this;
  }

  public function getApplicationDomain() {
    return $this->applicationDomain;
  }


  public function setApplicationType($application_type) {
    $this->applicationType = $application_type;
    return $this;
  }

  public function getApplicationType() {
    return $this->applicationType;
  }

  public function getFullName() {
    return coalesce(
      $this->getAttribute('fullname'),
      $this->getAttribute('name'),
      pht('External Object'));
  }

  public function getObjectKey() {
    if (!$this->objectKey) {
      $this->objectKey = PhabricatorHash::digestForIndex(
        implode(
          ':',
          array(
            $this->getApplicationType(),
            $this->getApplicationDomain(),
            $this->getObjectType(),
            $this->getObjectID(),
          )));
    }
    return $this->objectKey;
  }

}
