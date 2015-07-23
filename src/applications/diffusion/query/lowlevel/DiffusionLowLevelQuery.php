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
    if (!$this->getRepository()) {
      throw new PhutilInvalidStateException('setRepository');
    }

    return $this->executeQuery();
  }

  protected function filterRefsByType(array $refs, array $types) {
    $type_map = array_fuse($types);

    foreach ($refs as $name => $ref_list) {
      foreach ($ref_list as $key => $ref) {
        if (empty($type_map[$ref['type']])) {
          unset($refs[$name][$key]);
        }
      }
      if (!$refs[$name]) {
        unset($refs[$name]);
      }
    }

    return $refs;
  }

}
