<?php

final class PhameConstants extends Phobject {

  const VISIBILITY_DRAFT     = 0;
  const VISIBILITY_PUBLISHED = 1;

  public static function getPhamePostStatusMap() {
    return array(
      self::VISIBILITY_PUBLISHED  => pht('Published'),
      self::VISIBILITY_DRAFT => pht('Draft'),
    );
  }

  public static function getPhamePostStatusName($status) {
    $map = array(
      self::VISIBILITY_PUBLISHED => pht('Published'),
      self::VISIBILITY_DRAFT => pht('Draft'),
    );
    return idx($map, $status, pht('Unknown'));
  }

}
