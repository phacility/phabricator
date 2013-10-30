<?php

abstract class DiffusionLowLevelQuery extends Phobject {

  private $repository;

  abstract protected function executeQuery();

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->repository;
  }

  public function execute() {
    return $this->executeQuery();
  }

}
