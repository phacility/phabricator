<?php

/**
 * These exceptions represent user error, and are not logged.
 *
 * @concrete-extensible
 */
class AphrontUsageException extends AphrontException {

  private $title;

  public function __construct($title, $message) {
    $this->title = $title;
    parent::__construct($message);
  }

  public function getTitle() {
    return $this->title;
  }

}
