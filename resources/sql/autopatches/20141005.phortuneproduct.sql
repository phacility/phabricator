DROP TABLE {$NAMESPACE}_phortune.phortune_producttransaction;

ALTER TABLE {$NAMESPACE}_phortune.phortune_product
  DROP productName;

ALTER TABLE {$NAMESPACE}_phortune.phortune_product
  DROP priceAsCurrency;

ALTER TABLE {$NAMESPACE}_phortune.phortune_product
  ADD productClassKey BINARY(12) NOT NULL;

ALTER TABLE {$NAMESPACE}_phortune.phortune_product
  ADD productClass VARCHAR(128) NOT NULL COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_phortune.phortune_product
  ADD productRefKey BINARY(12) NOT NULL;

ALTER TABLE {$NAMESPACE}_phortune.phortune_product
  ADD productRef VARCHAR(128) NOT NULL COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_phortune.phortune_product
  ADD UNIQUE KEY `key_product` (productClassKey, productRefKey);
