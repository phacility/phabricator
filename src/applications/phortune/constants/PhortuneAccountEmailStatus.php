<?php

final class PhortuneAccountEmailStatus
  extends Phobject {

  const STATUS_ACTIVE = 'active';
  const STATUS_DISABLED = 'disabled';
  const STATUS_UNSUBSCRIBED = 'unsubscribed';

  public static function getDefaultStatusConstant() {
    return self::STATUS_ACTIVE;
  }

  private static function getMap() {
    return array(
      self::STATUS_ACTIVE => array(
        'name' => pht('Active'),
        'closed' => false,
      ),
      self::STATUS_DISABLED => array(
        'name' => pht('Disabled'),
        'closed' => true,
      ),
      self::STATUS_UNSUBSCRIBED => array(
        'name' => pht('Unsubscribed'),
        'closed' => true,
      ),
    );
  }

}
