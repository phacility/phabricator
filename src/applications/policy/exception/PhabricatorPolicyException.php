<?php

final class PhabricatorPolicyException extends Exception {

  private $title;
  private $rejection;
  private $capabilityName;
  private $moreInfo = array();

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

}
