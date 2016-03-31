/* Change quality from color to int */

UPDATE {$NAMESPACE}_badges.badges_badge
  SET quality = 140
  WHERE quality = 'grey';

UPDATE {$NAMESPACE}_badges.badges_badge
  SET quality = 120
  WHERE quality = 'white';

UPDATE {$NAMESPACE}_badges.badges_badge
  SET quality = 100
  WHERE quality = 'green';

UPDATE {$NAMESPACE}_badges.badges_badge
  SET quality = 80
  WHERE quality = 'blue';

UPDATE {$NAMESPACE}_badges.badges_badge
  SET quality = 60
  WHERE quality = 'indigo';

UPDATE {$NAMESPACE}_badges.badges_badge
  SET quality = 40
  WHERE quality = 'orange';

UPDATE {$NAMESPACE}_badges.badges_badge
  SET quality = 20
  WHERE quality = 'yellow';

ALTER TABLE {$NAMESPACE}_badges.badges_badge
  MODIFY quality INT UNSIGNED NOT NULL;
