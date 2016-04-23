<?php

final class PhabricatorEditEngineColumnsCommentAction
  extends PhabricatorEditEngineCommentAction {

  private $columnMap;

  public function setColumnMap(array $column_map) {
    $this->columnMap = $column_map;
    return $this;
  }

  public function getColumnMap() {
    return $this->columnMap;
  }

  public function getPHUIXControlType() {
    return 'optgroups';
  }

  public function getPHUIXControlSpecification() {
    return array(
      'groups' => $this->getColumnMap(),
    );
  }

}
