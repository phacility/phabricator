<?php

final class PhabricatorAuditStatusConstants extends Phobject {

  const AUDIT_REQUIRED          = 'audit-required';
  const CONCERNED               = 'concerned';
  const ACCEPTED                = 'accepted';
  const AUDIT_REQUESTED         = 'requested';
  const RESIGNED                = 'resigned';

  public static function getStatusNameMap() {
    $map = array(
      self::AUDIT_REQUIRED      => pht('Audit Required'),
      self::CONCERNED           => pht('Concern Raised'),
      self::ACCEPTED            => pht('Accepted'),
      self::AUDIT_REQUESTED     => pht('Audit Requested'),
      self::RESIGNED            => pht('Resigned'),
    );

    return $map;
  }

  public static function getActionRequiredStatusConstants() {
    return array(
      self::AUDIT_REQUIRED,
      self::AUDIT_REQUESTED,
    );
  }

  public static function getStatusName($code) {
    return idx(self::getStatusNameMap(), $code, pht('Unknown'));
  }

  public static function getStatusColor($code) {
    switch ($code) {
      case self::CONCERNED:
        $color = 'red';
        break;
      case self::AUDIT_REQUIRED:
      case self::AUDIT_REQUESTED:
        $color = 'orange';
        break;
      case self::ACCEPTED:
        $color = 'green';
        break;
      case self::RESIGNED:
        $color = 'grey';
        break;
      default:
        $color = 'bluegrey';
        break;
    }
    return $color;
  }

  public static function getStatusIcon($code) {
    switch ($code) {
      case self::AUDIT_REQUIRED:
      case self::AUDIT_REQUESTED:
        $icon = PHUIStatusItemView::ICON_WARNING;
        break;
      case self::CONCERNED:
        $icon = PHUIStatusItemView::ICON_REJECT;
        break;
      case self::ACCEPTED:
        $icon = PHUIStatusItemView::ICON_ACCEPT;
        break;
      case self::RESIGNED:
        $icon = 'fa-times';
        break;
      default:
        $icon = PHUIStatusItemView::ICON_QUESTION;
        break;
    }
    return $icon;
  }

  public static function getOpenStatusConstants() {
    return array(
      self::AUDIT_REQUIRED,
      self::AUDIT_REQUESTED,
      self::CONCERNED,
    );
  }

  public static function isOpenStatus($status) {
    return in_array($status, self::getOpenStatusConstants());
  }

}
