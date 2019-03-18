<?php

final class PhabricatorQueryCursor
  extends Phobject {

  private $object;

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

}
