ALTER TABLE {$NAMESPACE}_phortune.phortune_merchant
  ADD invoiceEmail VARCHAR(255) COLLATE {$COLLATE_TEXT} NOT NULL;

ALTER TABLE {$NAMESPACE}_phortune.phortune_merchant
  ADD invoiceFooter LONGTEXT COLLATE {$COLLATE_TEXT} NOT NULL;
