<?php

final class PhabricatorMailRoutingRule extends Phobject {

  const ROUTE_AS_NOTIFICATION = 'route.notification';
  const ROUTE_AS_MAIL = 'route.mail';

  public static function isStrongerThan($rule_u, $rule_v) {
    $strength_u = self::getRuleStrength($rule_u);
    $strength_v = self::getRuleStrength($rule_v);

    return ($strength_u > $strength_v);
  }

  public static function getRuleStrength($const) {
    $strength = array(
      self::ROUTE_AS_NOTIFICATION => 1,
      self::ROUTE_AS_MAIL => 2,
    );

    return idx($strength, $const, 0);
  }

  public static function getRuleName($const) {
    $names = array(
      self::ROUTE_AS_NOTIFICATION => pht('Route as Notification'),
      self::ROUTE_AS_MAIL => pht('Route as Mail'),
    );

    return idx($names, $const, $const);
  }

  public static function getRuleIcon($const) {
    $icons = array(
      self::ROUTE_AS_NOTIFICATION => 'fa-bell',
      self::ROUTE_AS_MAIL => 'fa-envelope',
    );

    return idx($icons, $const, 'fa-question-circle');
  }

  public static function getRuleColor($const) {
    $colors = array(
      self::ROUTE_AS_NOTIFICATION => 'grey',
      self::ROUTE_AS_MAIL => 'grey',
    );

    return idx($colors, $const, 'yellow');
  }

}
