ALTER TABLE {$NAMESPACE}_phame.phame_post
  ADD interactPolicy VARBINARY(64) NOT NULL;

UPDATE {$NAMESPACE}_phame.phame_post
  SET interactPolicy = 'obj.phame.blog'
  WHERE interactPolicy = '';
