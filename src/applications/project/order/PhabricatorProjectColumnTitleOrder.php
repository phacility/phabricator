<?php

final class PhabricatorProjectColumnTitleOrder
  extends PhabricatorProjectColumnOrder {

  const ORDERKEY = 'title';

  public function getDisplayName() {
    return pht('Sort by Title');
  }

  protected function newMenuIconIcon() {
    return 'fa-sort-alpha-asc';
  }

  public function getHasHeaders() {
    return false;
  }

  public function getCanReorder() {
    return false;
  }

  public function getMenuOrder() {
    return 7000;
  }

  protected function newSortVectorForObject($object) {
    return array(
      $object->getTitle(),
    );
  }

}
