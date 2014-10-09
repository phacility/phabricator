ALTER TABLE {$NAMESPACE}_phortune.phortune_charge
  ADD amountRefundedAsCurrency VARCHAR(64) NOT NULL COLLATE utf8_bin;

UPDATE {$NAMESPACE}_phortune.phortune_charge
  SET amountRefundedAsCurrency = '0.00 USD';

ALTER TABLE {$NAMESPACE}_phortune.phortune_charge
  ADD refundingPHID VARBINARY(64);

ALTER TABLE {$NAMESPACE}_phortune.phortune_charge
  ADD refundedChargePHID VARBINARY(64);
