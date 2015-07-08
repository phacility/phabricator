UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'phriction.document.title'
  WHERE r.contentType = 'PhrictionDocumentHeraldAdapter'
  AND c.fieldName = 'title';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'phriction.document.content'
  WHERE r.contentType = 'PhrictionDocumentHeraldAdapter'
  AND c.fieldName = 'body';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'phriction.document.author'
  WHERE r.contentType = 'PhrictionDocumentHeraldAdapter'
  AND c.fieldName = 'author';
