ALTER TABLE {$NAMESPACE}_file.file_imagemacro
  ADD audioPHID VARCHAR(64) COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_file.file_imagemacro
  ADD audioBehavior VARCHAR(64) NOT NULL COLLATE utf8_bin;

UPDATE {$NAMESPACE}_file.file_imagemacro
  SET audioBehavior = 'audio:none' WHERE audioBehavior = '';
