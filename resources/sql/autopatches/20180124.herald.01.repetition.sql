/* This column was previously "uint32?" with these values:

  1: run every time
  0: run only the first time

*/

UPDATE {$NAMESPACE}_herald.herald_rule
  SET repetitionPolicy = '1'
  WHERE repetitionPolicy IS NULL;

ALTER TABLE {$NAMESPACE}_herald.herald_rule
  CHANGE repetitionPolicy
    repetitionPolicy VARCHAR(32) NOT NULL COLLATE {$COLLATE_TEXT};

/* If the old value was "0", the new value is "first". */

UPDATE {$NAMESPACE}_herald.herald_rule
  SET repetitionPolicy = 'first'
  WHERE repetitionPolicy = '0';

/* If the old value was anything else, the new value is "every". */

UPDATE {$NAMESPACE}_herald.herald_rule
  SET repetitionPolicy = 'every'
  WHERE repetitionPolicy NOT IN ('first', '0');
