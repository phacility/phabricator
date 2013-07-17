<?php

final class PhabricatorMetaMTAActor {

  private $phid;
  private $emailAddress;
  private $name;
  private $reasons = array();

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setEmailAddress($email_address) {
    $this->emailAddress = $email_address;
    return $this;
  }

  public function getEmailAddress() {
    return $this->emailAddress;
  }

  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function getPHID() {
    return $this->phid;
  }

  public function setUndeliverable($reason) {
    $this->reasons[] = $reason;
    return $this;
  }

  public function isDeliverable() {
    return empty($this->reasons);
  }

  public function getUndeliverableReasons() {
    return $this->reasons;
  }

}
