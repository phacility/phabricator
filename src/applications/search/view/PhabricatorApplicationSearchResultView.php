<?php

final class PhabricatorApplicationSearchResultView extends Phobject {

/**
 * Holds bits and pieces of UI information for Search Engine
 * and Dashboard Panel rendering, describing the results and
 * controls for presentation.
 *
 */

  private $objectList = null;
  private $table = null;
  private $content = null;
  private $infoView = null;
  private $actions = array();
  private $collapsed = null;
  private $noDataString;

  public function setObjectList(PHUIObjectItemListView $list) {
    $this->objectList = $list;
    return $this;
  }

  public function getObjectList() {
    $list = $this->objectList;
    if ($list) {
      if ($this->noDataString) {
        $list->setNoDataString($this->noDataString);
      } else {
        $list->setNoDataString(pht('No results found for this query.'));
      }
    }
    return $list;
  }

  public function setTable($table) {
    $this->table = $table;
    return $this;
  }

  public function getTable() {
    return $this->table;
  }

  public function setInfoView(PHUIInfoView $infoview) {
    $this->infoView = $infoview;
    return $this;
  }

  public function getInfoView() {
    return $this->infoView;
  }

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function getContent() {
    return $this->content;
  }

  public function addAction(PHUIButtonView $button) {
    $this->actions[] = $button;
    return $this;
  }

  public function getActions() {
    return $this->actions;
  }

  public function setCollapsed($collapsed) {
    $this->collapsed = $collapsed;
    return $this;
  }

  public function getCollapsed() {
    return $this->collapsed;
  }

  public function setNoDataString($nodata) {
    $this->noDataString = $nodata;
    return $this;
  }

}
