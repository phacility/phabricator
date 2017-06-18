/* Extend from 12 characters to 64. */

ALTER TABLE {$NAMESPACE}_maniphest.maniphest_task
  CHANGE status status VARCHAR(64) COLLATE {$COLLATE_TEXT} NOT NULL;
