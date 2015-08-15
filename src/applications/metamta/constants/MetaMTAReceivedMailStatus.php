<?php

final class MetaMTAReceivedMailStatus
  extends Phobject {

  const STATUS_DUPLICATE            = 'err:duplicate';
  const STATUS_FROM_PHABRICATOR     = 'err:self';
  const STATUS_NO_RECEIVERS         = 'err:no-receivers';
  const STATUS_ABUNDANT_RECEIVERS   = 'err:multiple-receivers';
  const STATUS_UNKNOWN_SENDER       = 'err:unknown-sender';
  const STATUS_DISABLED_SENDER      = 'err:disabled-sender';
  const STATUS_NO_PUBLIC_MAIL       = 'err:no-public-mail';
  const STATUS_USER_MISMATCH        = 'err:bad-user';
  const STATUS_POLICY_PROBLEM       = 'err:policy';
  const STATUS_NO_SUCH_OBJECT       = 'err:not-found';
  const STATUS_HASH_MISMATCH        = 'err:bad-hash';
  const STATUS_UNHANDLED_EXCEPTION  = 'err:exception';
  const STATUS_EMPTY                = 'err:empty';
  const STATUS_EMPTY_IGNORED        = 'err:empty-ignored';

  public static function getHumanReadableName($status) {
    $map = array(
      self::STATUS_DUPLICATE => pht('Duplicate Message'),
      self::STATUS_FROM_PHABRICATOR => pht('Phabricator Mail'),
      self::STATUS_NO_RECEIVERS => pht('No Receivers'),
      self::STATUS_ABUNDANT_RECEIVERS => pht('Multiple Receivers'),
      self::STATUS_UNKNOWN_SENDER => pht('Unknown Sender'),
      self::STATUS_DISABLED_SENDER => pht('Disabled Sender'),
      self::STATUS_NO_PUBLIC_MAIL => pht('No Public Mail'),
      self::STATUS_USER_MISMATCH => pht('User Mismatch'),
      self::STATUS_POLICY_PROBLEM => pht('Policy Error'),
      self::STATUS_NO_SUCH_OBJECT => pht('No Such Object'),
      self::STATUS_HASH_MISMATCH => pht('Bad Address'),
      self::STATUS_UNHANDLED_EXCEPTION => pht('Unhandled Exception'),
      self::STATUS_EMPTY => pht('Empty Mail'),
      self::STATUS_EMPTY_IGNORED => pht('Ignored Empty Mail'),
    );

    return idx($map, $status, pht('Processing Exception'));
  }

}
