ALTER TABLE {$NAMESPACE}_legalpad.legalpad_documentsignature
  ADD signatureData LONGTEXT NOT NULL COLLATE utf8_bin AFTER signerPHID;
