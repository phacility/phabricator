<?php


final class PhabricatorEditEngineSubtype
  extends Phobject {

  const SUBTYPE_DEFAULT = 'default';

  public static function validateSubtypeKey($subtype) {
    if (strlen($subtype) > 64) {
      throw new Exception(
        pht(
          'Subtype "%s" is not valid: subtype keys must be no longer than '.
          '64 bytes.',
          $subtype));
    }

    if (strlen($subtype) < 3) {
      throw new Exception(
        pht(
          'Subtype "%s" is not valid: subtype keys must have a minimum '.
          'length of 3 bytes.',
          $subtype));
    }

    if (!preg_match('/^[a-z]+\z/', $subtype)) {
      throw new Exception(
        pht(
          'Subtype "%s" is not valid: subtype keys may only contain '.
          'lowercase latin letters ("a" through "z").',
          $subtype));
    }
  }


}
