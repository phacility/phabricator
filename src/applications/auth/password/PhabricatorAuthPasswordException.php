<?php

final class PhabricatorAuthPasswordException
  extends Exception {

  private $passwordError;
  private $confirmError;

  public function __construct(
    $message,
    $password_error,
    $confirm_error = null) {

    $this->passwordError = $password_error;
    $this->confirmError = $confirm_error;

    parent::__construct($message);
  }

  public function getPasswordError() {
    return $this->passwordError;
  }

  public function getConfirmError() {
    return $this->confirmError;
  }

}
