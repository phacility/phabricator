<?php

final class PhabricatorApplicationTransactionValidationException
  extends Exception {

  private $errors;

  public function __construct(array $errors) {
    assert_instances_of(
      $errors,
      'PhabricatorApplicationTransactionValidationError');

    $this->errors = $errors;

    $message = array();
    $message[] = pht('Validation errors:');
    foreach ($this->errors as $error) {
      $message[] = '  - '.$error->getMessage();
    }

    parent::__construct(implode("\n", $message));
  }

  public function getErrors() {
    return $this->errors;
  }

  public function getErrorMessages() {
    return mpull($this->errors, 'getMessage');
  }

  public function getShortMessage($type) {
    foreach ($this->errors as $error) {
      if ($error->getType() === $type) {
        if ($error->getShortMessage() !== null) {
          return $error->getShortMessage();
        }
      }
    }
    return null;
  }

}
