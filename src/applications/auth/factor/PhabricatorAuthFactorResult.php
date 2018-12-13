<?php

final class PhabricatorAuthFactorResult
  extends Phobject {

  private $isValid = false;
  private $isWait = false;
  private $errorMessage;
  private $value;
  private $issuedChallenges = array();

  public function setIsValid($is_valid) {
    $this->isValid = $is_valid;
    return $this;
  }

  public function getIsValid() {
    return $this->isValid;
  }

  public function setIsWait($is_wait) {
    $this->isWait = $is_wait;
    return $this;
  }

  public function getIsWait() {
    return $this->isWait;
  }

  public function setErrorMessage($error_message) {
    $this->errorMessage = $error_message;
    return $this;
  }

  public function getErrorMessage() {
    return $this->errorMessage;
  }

  public function setValue($value) {
    $this->value = $value;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  public function setIssuedChallenges(array $issued_challenges) {
    assert_instances_of($issued_challenges, 'PhabricatorAuthChallenge');
    $this->issuedChallenges = $issued_challenges;
    return $this;
  }

  public function getIssuedChallenges() {
    return $this->issuedChallenges;
  }

}
