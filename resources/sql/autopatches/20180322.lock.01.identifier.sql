ALTER TABLE {$NAMESPACE}_repository.repository_pushevent
  ADD requestIdentifier VARBINARY(12);

ALTER TABLE {$NAMESPACE}_repository.repository_pushevent
  ADD UNIQUE KEY `key_request` (requestIdentifier);
