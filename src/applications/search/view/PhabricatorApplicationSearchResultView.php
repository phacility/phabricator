<?php

/**
 * Holds bits and pieces of UI information for Search Engine
 * and Dashboard Panel rendering, describing the results and
 * controls for presentation.
 */
final class PhabricatorApplicationSearchResultView extends Phobject {

  private $objectList = null;
  private $table = null;
  private $content = null;
  private $infoView = null;
  private $actions = array();
  private $noDataString;
  private $crumbs = array();
  private $header;

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

  public function setNoDataString($nodata) {
    $this->noDataString = $nodata;
    return $this;
  }

  public function setCrumbs(array $crumbs) {
    assert_instances_of($crumbs, 'PHUICrumbView');

    $this->crumbs = $crumbs;
    return $this;
  }

  public function getCrumbs() {
    return $this->crumbs;
  }

  public function setHeader(PHUIHeaderView $header) {
    $this->header = $header;
    return $this;
  }

  public function getHeader() {
    return $this->header;
  }



}
