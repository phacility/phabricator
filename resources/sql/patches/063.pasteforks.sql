ALTER TABLE {$NAMESPACE}_pastebin.pastebin_paste
  ADD COLUMN parentPHID VARCHAR(64) BINARY,
  ADD KEY (parentPHID);
