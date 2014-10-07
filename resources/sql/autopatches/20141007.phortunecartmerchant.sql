ALTER TABLE {$NAMESPACE}_phortune.phortune_cart
  ADD merchantPHID VARBINARY(64) NOT NULL;

ALTER TABLE {$NAMESPACE}_phortune.phortune_cart
  ADD KEY `key_merchant` (merchantPHID);
