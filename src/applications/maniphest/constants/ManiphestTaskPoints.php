<?php

final class ManiphestTaskPoints extends Phobject {

  public static function getIsEnabled() {
    $config = self::getPointsConfig();
    return idx($config, 'enabled');
  }

  public static function getPointsLabel() {
    $config = self::getPointsConfig();
    return idx($config, 'label', pht('Points'));
  }

  public static function getPointsActionLabel() {
    $config = self::getPointsConfig();
    return idx($config, 'action', pht('Change Points'));
  }

  private static function getPointsConfig() {
    return PhabricatorEnv::getEnvConfig('maniphest.points');
  }

}
