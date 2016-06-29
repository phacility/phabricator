<?php

final class PhabricatorApplicationTransactionValidationError
  extends Phobject {

  private $type;
  private $transaction;
  private $shortMessage;
  private $message;
  private $isMissingFieldError;

  public function __construct(
    $type,
    $short_message,
    $message,
    PhabricatorApplicationTransaction $xaction = null) {

    $this->type = $type;
    $this->shortMessage = $short_message;
    $this->message = $message;
    $this->transaction = $xaction;
  }

  public function getType() {
    return $this->type;
  }

  public function getTransaction() {
    return $this->transaction;
  }

  public function getShortMessage() {
    return $this->shortMessage;
  }

  public function getMessage() {
    return $this->message;
  }

  public function setIsMissingFieldError($is_missing_field_error) {
    $this->isMissingFieldError = $is_missing_field_error;
    return $this;
  }

  public function getIsMissingFieldError() {
    return $this->isMissingFieldError;
  }

}
