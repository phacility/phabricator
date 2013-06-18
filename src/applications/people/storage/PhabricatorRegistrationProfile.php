<?php

final class PhabricatorRegistrationProfile extends Phobject {

  private $defaultUsername;
  private $defaultEmail;
  private $defaultRealName;
  private $canEditUsername;
  private $canEditEmail;
  private $canEditRealName;
  private $shouldVerifyEmail;

  public function setShouldVerifyEmail($should_verify_email) {
    $this->shouldVerifyEmail = $should_verify_email;
    return $this;
  }

  public function getShouldVerifyEmail() {
    return $this->shouldVerifyEmail;
  }

  public function setCanEditEmail($can_edit_email) {
    $this->canEditEmail = $can_edit_email;
    return $this;
  }

  public function getCanEditEmail() {
    return $this->canEditEmail;
  }

  public function setCanEditRealName($can_edit_real_name) {
    $this->canEditRealName = $can_edit_real_name;
    return $this;
  }

  public function getCanEditRealName() {
    return $this->canEditRealName;
  }


  public function setCanEditUsername($can_edit_username) {
    $this->canEditUsername = $can_edit_username;
    return $this;
  }

  public function getCanEditUsername() {
    return $this->canEditUsername;
  }

  public function setDefaultEmail($default_email) {
    $this->defaultEmail = $default_email;
    return $this;
  }

  public function getDefaultEmail() {
    return $this->defaultEmail;
  }

  public function setDefaultRealName($default_real_name) {
    $this->defaultRealName = $default_real_name;
    return $this;
  }

  public function getDefaultRealName() {
    return $this->defaultRealName;
  }


  public function setDefaultUsername($default_username) {
    $this->defaultUsername = $default_username;
    return $this;
  }

  public function getDefaultUsername() {
    return $this->defaultUsername;
  }

  public function getCanEditAnything() {
    return $this->getCanEditUsername() ||
           $this->getCanEditEmail() ||
           $this->getCanEditRealName();
  }

}
