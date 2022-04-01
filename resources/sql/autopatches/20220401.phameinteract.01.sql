UPDATE {$NAMESPACE}_phame.phame_blog
  SET editPolicy = 'admin' WHERE editPolicy IS NULL;

ALTER TABLE {$NAMESPACE}_phame.phame_blog
  CHANGE editPolicy editPolicy VARBINARY(64) NOT NULL;
