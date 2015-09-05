<?php

final class PhabricatorPolicyException extends Exception {

  private $title;
  private $rejection;
  private $capabilityName;
  private $moreInfo = array();
  private $objectPHID;
  private $context;
  private $capability;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  public function setCapabilityName($capability_name) {
    $this->capabilityName = $capability_name;
    return $this;
  }

  public function getCapabilityName() {
    return $this->capabilityName;
  }

  public function setRejection($rejection) {
    $this->rejection = $rejection;
    return $this;
  }

  public function getRejection() {
    return $this->rejection;
  }

  public function setMoreInfo(array $more_info) {
    $this->moreInfo = $more_info;
    return $this;
  }

  public function getMoreInfo() {
    return $this->moreInfo;
  }

  public function setObjectPHID($object_phid) {
    $this->objectPHID = $object_phid;
    return $this;
  }

  public function getObjectPHID() {
    return $this->objectPHID;
  }

  public function setContext($context) {
    $this->context = $context;
    return $this;
  }

  public function getContext() {
    return $this->context;
  }

  public function setCapability($capability) {
    $this->capability = $capability;
    return $this;
  }

  public function getCapability() {
    return $this->capability;
  }

}
