UPDATE {$NAMESPACE}_phriction.phriction_document
  SET status = 'active' WHERE status = '0';

UPDATE {$NAMESPACE}_phriction.phriction_document
  SET status = 'deleted' WHERE status = '1';

UPDATE {$NAMESPACE}_phriction.phriction_document
  SET status = 'moved' WHERE status = '2';

UPDATE {$NAMESPACE}_phriction.phriction_document
  SET status = 'stub' WHERE status = '3';
