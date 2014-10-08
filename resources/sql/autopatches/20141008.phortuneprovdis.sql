ALTER TABLE {$NAMESPACE}_phortune.phortune_paymentproviderconfig
  ADD isEnabled BOOL NOT NULL;

UPDATE {$NAMESPACE}_phortune.phortune_paymentproviderconfig
  SET isEnabled = 1;
