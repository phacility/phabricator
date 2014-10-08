TRUNCATE {$NAMESPACE}_phortune.phortune_purchase;

ALTER TABLE {$NAMESPACE}_phortune.phortune_purchase
  DROP totalPriceInCents;

ALTER TABLE {$NAMESPACE}_phortune.phortune_purchase
  CHANGE basePriceInCents basePriceAsCurrency VARCHAR(64)
  NOT NULL collate utf8_bin;
