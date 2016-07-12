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

  public static function validateConfiguration($config) {
    if (!is_array($config)) {
      throw new Exception(
        pht(
          'Configuration is not valid. Maniphest points configuration must '.
          'be a dictionary.'));
    }

    PhutilTypeSpec::checkMap(
      $config,
      array(
        'enabled' => 'optional bool',
        'label' => 'optional string',
        'action' => 'optional string',
      ));
  }

}
