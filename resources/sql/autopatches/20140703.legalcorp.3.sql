ALTER TABLE {$NAMESPACE}_legalpad.legalpad_documentsignature
  ADD signatureType VARCHAR(4) NOT NULL COLLATE utf8_bin AFTER documentVersion;
