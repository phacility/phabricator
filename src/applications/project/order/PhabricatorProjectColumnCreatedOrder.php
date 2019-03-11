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

  protected function newSortVectorForObject($object) {
    return array(
      (int)-$object->getDateCreated(),
      (int)-$object->getID(),
    );
  }

}
