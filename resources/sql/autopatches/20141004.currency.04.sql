TRUNCATE {$NAMESPACE}_phortune.phortune_product;

ALTER TABLE {$NAMESPACE}_phortune.phortune_product
  DROP status;

ALTER TABLE {$NAMESPACE}_phortune.phortune_product
  DROP billingIntervalInMonths;

ALTER TABLE {$NAMESPACE}_phortune.phortune_product
  DROP trialPeriodInDays;

ALTER TABLE {$NAMESPACE}_phortune.phortune_product
  CHANGE priceInCents priceAsCurrency VARCHAR(64) NOT NULL collate utf8_bin;
