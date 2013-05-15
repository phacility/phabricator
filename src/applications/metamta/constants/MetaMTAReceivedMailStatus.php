<?php

final class MetaMTAReceivedMailStatus
  extends MetaMTAConstants {

  const STATUS_DUPLICATE            = 'err:duplicate';
  const STATUS_FROM_PHABRICATOR     = 'err:self';
  const STATUS_NO_RECEIVERS         = 'err:no-receivers';
  const STATUS_ABUNDANT_RECEIVERS   = 'err:multiple-receivers';
  const STATUS_UNKNOWN_SENDER       = 'err:unknown-sender';

}
