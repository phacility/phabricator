TRUNCATE TABLE {$NAMESPACE}_phortune.phortune_cart;

ALTER TABLE {$NAMESPACE}_phortune.phortune_cart
  ADD cartClass VARCHAR(128) NOT NULL COLLATE utf8_bin;
