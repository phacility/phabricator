<?php

abstract class PhabricatorConfigGroupConstants
  extends PhabricatorConfigConstants {

  const GROUP_CORE = 'core';
  const GROUP_APPLICATION = 'apps';
  const GROUP_DEVELOPER = 'developer';

  public static function getGroupName($group) {
    $map = array(
      self::GROUP_CORE  => pht('Core Settings'),
      self::GROUP_APPLICATION => pht('Application Settings'),
      self::GROUP_DEVELOPER => pht('Developer Settings'),
    );
    return idx($map, $group, pht('Unknown'));
  }

  public static function getGroupShortName($group) {
    $map = array(
      self::GROUP_CORE  => pht('Core'),
      self::GROUP_APPLICATION => pht('Application'),
      self::GROUP_DEVELOPER => pht('Developer'),
    );
    return idx($map, $group, pht('Unknown'));
  }

  public static function getGroupURI($group) {
    $map = array(
      self::GROUP_CORE  => '/',
      self::GROUP_APPLICATION => pht('application/'),
      self::GROUP_DEVELOPER => pht('developer/'),
    );
    return idx($map, $group, '#');
  }

}
