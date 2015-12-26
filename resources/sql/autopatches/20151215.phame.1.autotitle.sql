ALTER TABLE {$NAMESPACE}_phame.phame_post
  DROP KEY phameTitle;

ALTER TABLE {$NAMESPACE}_phame.phame_post
  CHANGE phameTitle phameTitle VARCHAR(64) COLLATE {$COLLATE_SORT};
