ALTER TABLE {$NAMESPACE}_legalpad.legalpad_documentsignature
  ADD secretKey VARCHAR(20) NOT NULL COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_legalpad.legalpad_documentsignature
  ADD verified TINYINT(1) DEFAULT 0;

ALTER TABLE {$NAMESPACE}_legalpad.legalpad_documentsignature
  ADD KEY `secretKey` (secretKey);
