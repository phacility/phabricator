<?php

final class DifferentialLegacyQuery
  extends Phobject {

  const STATUS_ANY            = 'status-any';
  const STATUS_OPEN           = 'status-open';
  const STATUS_ACCEPTED       = 'status-accepted';
  const STATUS_NEEDS_REVIEW   = 'status-needs-review';
  const STATUS_NEEDS_REVISION = 'status-needs-revision';
  const STATUS_CLOSED         = 'status-closed';
  const STATUS_ABANDONED      = 'status-abandoned';

  public static function getAllConstants() {
    return array_keys(self::getMap());
  }

  public static function getModernValues($status) {
    if ($status === self::STATUS_ANY) {
      return null;
    }

    $map = self::getMap();
    if (!isset($map[$status])) {
      throw new Exception(
        pht(
          'Unknown revision status filter constant "%s".',
          $status));
    }

    return $map[$status];
  }

  private static function getMap() {
    $all = array(
      DifferentialRevisionStatus::NEEDS_REVIEW,
      DifferentialRevisionStatus::NEEDS_REVISION,
      DifferentialRevisionStatus::CHANGES_PLANNED,
      DifferentialRevisionStatus::ACCEPTED,
      DifferentialRevisionStatus::PUBLISHED,
      DifferentialRevisionStatus::ABANDONED,
    );

    $open = array();
    $closed = array();

    foreach ($all as $status) {
      $status_object = DifferentialRevisionStatus::newForStatus($status);
      if ($status_object->isClosedStatus()) {
        $closed[] = $status_object->getKey();
      } else {
        $open[] = $status_object->getKey();
      }
    }

    return array(
      self::STATUS_ANY => $all,
      self::STATUS_OPEN => $open,
      self::STATUS_ACCEPTED => array(
        DifferentialRevisionStatus::ACCEPTED,
      ),
      self::STATUS_NEEDS_REVIEW => array(
        DifferentialRevisionStatus::NEEDS_REVIEW,
      ),
      self::STATUS_NEEDS_REVISION => array(
        DifferentialRevisionStatus::NEEDS_REVISION,
      ),
      self::STATUS_CLOSED => $closed,
      self::STATUS_ABANDONED => array(
        DifferentialRevisionStatus::ABANDONED,
      ),
    );
  }

}
