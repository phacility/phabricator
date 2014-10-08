ALTER TABLE {$NAMESPACE}_legalpad.legalpad_documentsignature
  ADD isExemption BOOL NOT NULL DEFAULT 0 AFTER verified;
