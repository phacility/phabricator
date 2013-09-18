<?php

final class PhabricatorApplicationTransactionValidationError
  extends Phobject {

  private $type;
  private $transaction;
  private $shortMessage;
  private $message;

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
    return $this->tranaction;
  }

  public function getShortMessage() {
    return $this->shortMessage;
  }

  public function getMessage() {
    return $this->message;
  }

}
