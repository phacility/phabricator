<?php

final class PhabricatorDashboardPanelRef
  extends Phobject {

  private $panelPHID;
  private $panelKey;
  private $columnKey;

  public function setPanelPHID($panel_phid) {
    $this->panelPHID = $panel_phid;
    return $this;
  }

  public function getPanelPHID() {
    return $this->panelPHID;
  }

  public function setColumnKey($column_key) {
    $this->columnKey = $column_key;
    return $this;
  }

  public function getColumnKey() {
    return $this->columnKey;
  }

  public function setPanelKey($panel_key) {
    $this->panelKey = $panel_key;
    return $this;
  }

  public function getPanelKey() {
    return $this->panelKey;
  }

  public function toDictionary() {
    return array(
      'panelKey' => $this->getPanelKey(),
      'panelPHID' => $this->getPanelPHID(),
      'columnKey' => $this->getColumnKey(),
    );
  }

}
