<?php

final class PhabricatorFlag extends PhabricatorFlagDAO {

  protected $ownerPHID;
  protected $type;
  protected $objectPHID;
  protected $reasonPHID;
  protected $color = PhabricatorFlagColor::COLOR_BLUE;
  protected $note;

  private $handle = false;
  private $object = false;

  public function getObject() {
    if ($this->object === false) {
      throw new Exception('Call attachObject() before getObject()!');
    }
    return $this->object;
  }

  public function attachObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getHandle() {
    if ($this->handle === false) {
      throw new Exception('Call attachHandle() before getHandle()!');
    }
    return $this->handle;
  }

  public function attachHandle(PhabricatorObjectHandle $handle) {
    $this->handle = $handle;
    return $this;
  }

}
