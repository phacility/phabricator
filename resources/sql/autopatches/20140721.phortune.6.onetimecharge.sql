ALTER TABLE {$NAMESPACE}_phortune.phortune_charge
  ADD paymentProviderKey VARCHAR(128) NOT NULL COLLATE utf8_bin
  AFTER cartPHID;
