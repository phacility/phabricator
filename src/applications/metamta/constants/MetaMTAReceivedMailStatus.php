<?php

final class MetaMTAReceivedMailStatus
  extends MetaMTAConstants {

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

}
