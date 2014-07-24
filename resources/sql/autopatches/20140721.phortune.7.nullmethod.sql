/* Make this nullable to support one-time providers. */

ALTER TABLE {$NAMESPACE}_phortune.phortune_charge
  CHANGE paymentMethodPHID paymentMethodPHID VARCHAR(64) COLLATE utf8_bin;
