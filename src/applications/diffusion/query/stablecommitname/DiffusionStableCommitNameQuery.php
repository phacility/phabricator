<?php

abstract class DiffusionStableCommitNameQuery extends DiffusionQuery {

  private $branch;
  private $repository;

  public function setBranch($branch) {
    $this->branch = $branch;
    return $this;
  }
  public function getBranch() {
    return $this->branch;
  }

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }
  public function getRepository() {
    return $this->repository;
  }

  final public static function newFromRepository(
    PhabricatorRepository $repository) {

    $obj = parent::initQueryObject(__CLASS__, $repository);
    $obj->setRepository($repository);
    return $obj;
  }

  final public function load() {
    return $this->executeQuery();
  }
}
