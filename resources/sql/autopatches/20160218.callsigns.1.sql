/* Make callsigns nullable, and thus optional. */

ALTER TABLE {$NAMESPACE}_repository.repository
  CHANGE callsign callsign VARCHAR(32) COLLATE {$COLLATE_SORT};
