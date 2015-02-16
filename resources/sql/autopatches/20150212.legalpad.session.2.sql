ALTER TABLE {$NAMESPACE}_legalpad.legalpad_document
  ADD KEY `key_required` (requireSignature, dateModified);
