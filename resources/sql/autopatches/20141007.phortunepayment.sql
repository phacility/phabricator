TRUNCATE TABLE {$NAMESPACE}_phortune.phortune_paymentmethod;

ALTER TABLE {$NAMESPACE}_phortune.phortune_paymentmethod
  DROP providerType;

ALTER TABLE {$NAMESPACE}_phortune.phortune_paymentmethod
  DROP providerDomain;

ALTER TABLE {$NAMESPACE}_phortune.phortune_paymentmethod
  ADD merchantPHID VARBINARY(64) NOT NULL;

ALTER TABLE {$NAMESPACE}_phortune.phortune_paymentmethod
  ADD providerPHID VARBINARY(64) NOT NULL;

ALTER TABLE {$NAMESPACE}_phortune.phortune_paymentmethod
  ADD KEY `key_merchant` (merchantPHID, accountPHID);
