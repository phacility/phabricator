TRUNCATE TABLE {$NAMESPACE}_phortune.phortune_paymentmethod;

ALTER TABLE {$NAMESPACE}_phortune.phortune_paymentmethod
  ADD brand VARCHAR(64) NOT NULL;

ALTER TABLE {$NAMESPACE}_phortune.phortune_paymentmethod
  ADD expires VARCHAR(16) NOT NULL;

ALTER TABLE {$NAMESPACE}_phortune.phortune_paymentmethod
  ADD providerType VARCHAR(16) NOT NULL;

ALTER TABLE {$NAMESPACE}_phortune.phortune_paymentmethod
  ADD providerDomain VARCHAR(64) NOT NULL;

ALTER TABLE {$NAMESPACE}_phortune.phortune_paymentmethod
  ADD lastFourDigits VARCHAR(16) NOT NULL;

ALTER TABLE {$NAMESPACE}_phortune.phortune_paymentmethod
  DROP expiresEpoch;
