<?php

final class PhabricatorDashboardColumn
  extends Phobject {

  private $columnKey;
  private $classes = array();

  public function setColumnKey($column_key) {
    $this->columnKey = $column_key;
    return $this;
  }

  public function getColumnKey() {
    return $this->columnKey;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function getClasses() {
    return $this->classes;
  }

}
