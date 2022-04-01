UPDATE {$NAMESPACE}_phame.phame_blog
  SET viewPolicy = 'admin' WHERE viewPolicy IS NULL;

ALTER TABLE {$NAMESPACE}_phame.phame_blog
  CHANGE viewPolicy viewPolicy VARBINARY(64) NOT NULL;
