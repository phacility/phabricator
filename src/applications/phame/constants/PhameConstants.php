<?php

final class PhameConstants extends Phobject {

  const VISIBILITY_DRAFT = 0;
  const VISIBILITY_PUBLISHED = 1;
  const VISIBILITY_ARCHIVED = 2;

  public static function getPhamePostStatusMap() {
    return array(
      self::VISIBILITY_PUBLISHED  => pht('Published'),
      self::VISIBILITY_DRAFT => pht('Draft'),
      self::VISIBILITY_ARCHIVED => pht('Archived'),
    );
  }

  public static function getPhamePostStatusName($status) {
    $map = array(
      self::VISIBILITY_PUBLISHED => pht('Published'),
      self::VISIBILITY_DRAFT => pht('Draft'),
      self::VISIBILITY_ARCHIVED => pht('Archived'),
    );
    return idx($map, $status, pht('Unknown'));
  }

}
