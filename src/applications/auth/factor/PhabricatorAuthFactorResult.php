<?php

final class PhabricatorAuthFactorResult
  extends Phobject {

  private $answeredChallenge;
  private $isWait = false;
  private $isError = false;
  private $isContinue = false;
  private $errorMessage;
  private $value;
  private $issuedChallenges = array();
  private $icon;
  private $statusChallenge;

  public function setAnsweredChallenge(PhabricatorAuthChallenge $challenge) {
    if (!$challenge->getIsAnsweredChallenge()) {
      throw new PhutilInvalidStateException('markChallengeAsAnswered');
    }

    if ($challenge->getIsCompleted()) {
      throw new Exception(
        pht(
          'A completed challenge was provided as an answered challenge. '.
          'The underlying factor is implemented improperly, challenges '.
          'may not be reused.'));
    }

    $this->answeredChallenge = $challenge;

    return $this;
  }

  public function getAnsweredChallenge() {
    return $this->answeredChallenge;
  }

  public function setStatusChallenge(PhabricatorAuthChallenge $challenge) {
    $this->statusChallenge = $challenge;
    return $this;
  }

  public function getStatusChallenge() {
    return $this->statusChallenge;
  }

  public function getIsValid() {
    return (bool)$this->getAnsweredChallenge();
  }

  public function setIsWait($is_wait) {
    $this->isWait = $is_wait;
    return $this;
  }

  public function getIsWait() {
    return $this->isWait;
  }

  public function setIsError($is_error) {
    $this->isError = $is_error;
    return $this;
  }

  public function getIsError() {
    return $this->isError;
  }

  public function setIsContinue($is_continue) {
    $this->isContinue = $is_continue;
    return $this;
  }

  public function getIsContinue() {
    return $this->isContinue;
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

  public function setIcon(PHUIIconView $icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

}
