<?php

final class PhabricatorSystemActionRateLimitException extends Exception {

  private $action;
  private $score;

  public function __construct(PhabricatorSystemAction $action, $score) {
    $this->action = $action;
    $this->score = $score;
    parent::__construct($action->getLimitExplanation());
  }

  public function getRateExplanation() {
    return $this->action->getRateExplanation($this->score);
  }

}
