<?php

abstract class PhabricatorUIExample extends Phobject {

  private $request;

  public function setRequest($request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  abstract public function getName();
  abstract public function getDescription();
  abstract public function renderExample();

  protected function createBasicDummyHandle($name, $type, $fullname = null,
    $uri = null) {

    $id = mt_rand(15, 9999);
    $handle = new PhabricatorObjectHandle();
    $handle->setName($name);
    $handle->setType($type);
    $handle->setPHID(PhabricatorPHID::generateNewPHID($type));

    if ($fullname) {
      $handle->setFullName($fullname);
    } else {
      $handle->setFullName(
        sprintf('%s%d: %s',
          substr($type, 0, 1),
          $id,
          $name));
    }

    if ($uri) {
      $handle->setURI($uri);
    }

    return $handle;
  }

}
