/* Removes Ponder vote data. */

DELETE FROM {$NAMESPACE}_ponder.edge
  WHERE type IN (17, 18, 19, 20);

DELETE FROM {$NAMESPACE}_user.edge
  WHERE type IN (17, 18, 19, 20);
