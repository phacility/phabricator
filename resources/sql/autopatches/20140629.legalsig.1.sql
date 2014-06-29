ALTER TABLE {$NAMESPACE}_legalpad.legalpad_documentsignature
  ADD signerName VARCHAR(255) NOT NULL COLLATE utf8_general_ci
  AFTER signerPHID;

ALTER TABLE {$NAMESPACE}_legalpad.legalpad_documentsignature
  ADD signerEmail VARCHAR(255) NOT NULL COLLATE utf8_general_ci
  AFTER signerName;
