ALTER TABLE {$NAMESPACE}_pastebin.pastebin_paste
  ADD KEY `key_dateCreated` (dateCreated);

ALTER TABLE {$NAMESPACE}_pastebin.pastebin_paste
  ADD KEY `key_language` (language);
