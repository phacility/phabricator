<?php

final class PhabricatorQueryCursor
  extends Phobject {

  private $object;
  private $rawRow;

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  public function setRawRow(array $raw_row) {
    $this->rawRow = $raw_row;
    return $this;
  }

  public function getRawRow() {
    return $this->rawRow;
  }

  public function getRawRowProperty($key) {
    if (!is_array($this->rawRow)) {
      throw new Exception(
        pht(
          'Caller is trying to "getRawRowProperty()" with key "%s", but this '.
          'cursor has no raw row.',
          $key));
    }

    if (!array_key_exists($key, $this->rawRow)) {
      throw new Exception(
        pht(
          'Caller is trying to access raw row property "%s", but the row '.
          'does not have this property.',
          $key));
    }

    return $this->rawRow[$key];
  }

}
