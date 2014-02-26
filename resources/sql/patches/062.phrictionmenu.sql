/* Older versions incorrectly computed the depth for the root page. */
UPDATE {$NAMESPACE}_phriction.phriction_document
  SET depth = 0 where slug = '/';
