TRUNCATE TABLE {$NAMESPACE}_phortune.phortune_charge;

ALTER TABLE {$NAMESPACE}_phortune.phortune_charge
  DROP paymentProviderKey;

ALTER TABLE {$NAMESPACE}_phortune.phortune_charge
  ADD merchantPHID VARBINARY(64) NOT NULL;

ALTER TABLE {$NAMESPACE}_phortune.phortune_charge
  ADD providerPHID VARBINARY(64) NOT NULL;

ALTER TABLE {$NAMESPACE}_phortune.phortune_charge
  ADD KEY `key_merchant` (merchantPHID);

ALTER TABLE {$NAMESPACE}_phortune.phortune_charge
  ADD KEY `key_provider` (providerPHID);
