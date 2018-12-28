<?php

final class PhabricatorAuthFactorResult
  extends Phobject {

  private $answeredChallenge;
  private $isWait = false;
  private $errorMessage;
  private $value;
  private $issuedChallenges = array();

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
