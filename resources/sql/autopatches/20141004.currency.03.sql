TRUNCATE {$NAMESPACE}_phortune.phortune_charge;

ALTER TABLE {$NAMESPACE}_phortune.phortune_charge
  CHANGE amountInCents amountAsCurrency VARCHAR(64) NOT NULL COLLATE utf8_bin;
