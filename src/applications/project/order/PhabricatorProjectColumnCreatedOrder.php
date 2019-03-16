<?php

final class PhabricatorProjectColumnCreatedOrder
  extends PhabricatorProjectColumnOrder {

  const ORDERKEY = 'created';

  public function getDisplayName() {
    return pht('Sort by Created Date');
  }

  protected function newMenuIconIcon() {
    return 'fa-clock-o';
  }

  public function getHasHeaders() {
    return false;
  }

  public function getCanReorder() {
    return false;
  }

  public function getMenuOrder() {
    return 5000;
  }

  protected function newSortVectorForObject($object) {
    return array(
      -1 * (int)$object->getDateCreated(),
      -1 * (int)$object->getID(),
    );
  }

}
