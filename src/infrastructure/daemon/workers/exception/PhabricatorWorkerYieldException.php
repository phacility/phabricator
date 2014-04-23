<?php

/**
 * Allows tasks to yield to other tasks.
 *
 * If a worker throws this exception while processing a task, the task will be
 * pushed toward the back of the queue and tried again later.
 */
final class PhabricatorWorkerYieldException extends Exception {

  private $duration;

  public function __construct($duration) {
    $this->duration = $duration;
    parent::__construct();
  }

  public function getDuration() {
    return $this->duration;
  }

}
