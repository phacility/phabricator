ALTER TABLE {$NAMESPACE}_file.file
  ADD KEY `key_partial` (authorPHID, isPartial);
