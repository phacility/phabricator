UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'pholio.mock.name'
  WHERE r.contentType = 'HeraldPholioMockAdapter'
  AND c.fieldName = 'title';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'pholio.mock.description'
  WHERE r.contentType = 'HeraldPholioMockAdapter'
  AND c.fieldName = 'body';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'pholio.mock.author'
  WHERE r.contentType = 'HeraldPholioMockAdapter'
  AND c.fieldName = 'author';
