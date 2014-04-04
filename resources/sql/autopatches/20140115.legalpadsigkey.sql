ALTER TABLE {$NAMESPACE}_legalpad.legalpad_documentsignature
  DROP KEY `key_document`;

ALTER TABLE {$NAMESPACE}_legalpad.legalpad_documentsignature
  ADD KEY `key_document` (`documentPHID`,`signerPHID`, `documentVersion`);
