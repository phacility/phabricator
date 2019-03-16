<?php

final class PhabricatorProjectColumnPointsOrder
  extends PhabricatorProjectColumnOrder {

  const ORDERKEY = 'points';

  public function getDisplayName() {
    return pht('Sort by Points');
  }

  protected function newMenuIconIcon() {
    return 'fa-map-pin';
  }

  public function isEnabled() {
    return ManiphestTaskPoints::getIsEnabled();
  }

  public function getHasHeaders() {
    return false;
  }

  public function getCanReorder() {
    return false;
  }

  public function getMenuOrder() {
    return 6000;
  }

  protected function newSortVectorForObject($object) {
    $points = $object->getPoints();

    // Put cards with no points on top.
    $has_points = ($points !== null);
    if (!$has_points) {
      $overall_order = 0;
    } else {
      $overall_order = 1;
    }

    return array(
      $overall_order,
      -1.0 * (double)$points,
      -1 * (int)$object->getID(),
    );
  }

}
