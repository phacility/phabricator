ALTER TABLE {$NAMESPACE}_phame.phame_blog
  ADD interactPolicy VARBINARY(64) NOT NULL;

UPDATE {$NAMESPACE}_phame.phame_blog
  SET interactPolicy = 'users'
  WHERE interactPolicy = '';
