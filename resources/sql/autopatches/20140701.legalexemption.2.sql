ALTER TABLE {$NAMESPACE}_legalpad.legalpad_documentsignature
  ADD exemptionPHID VARCHAR(64) COLLATE utf8_bin AFTER isExemption;
