ALTER TABLE {$NAMESPACE}_file.file
  ADD contentHash varchar(40) COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_file.file
  ADD KEY (contentHash);
