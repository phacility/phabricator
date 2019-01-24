<?php

final class PhabricatorAuthFactorResultException
  extends Exception {

  private $result;

  public function __construct(PhabricatorAuthFactorResult $result) {
    $this->result = $result;
    parent::__construct();
  }

  public function getResult() {
    return $this->result;
  }

}
