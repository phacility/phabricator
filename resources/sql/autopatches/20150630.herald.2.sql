# This converts old conditions which use common fields like "body" to new
# conditions which use modular rules like "Maniphest Task Description".

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'maniphest.task.title'
  WHERE r.contentType = 'HeraldManiphestTaskAdapter'
  AND c.fieldName = 'title';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'maniphest.task.description'
  WHERE r.contentType = 'HeraldManiphestTaskAdapter'
  AND c.fieldName = 'body';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'maniphest.task.author'
  WHERE r.contentType = 'HeraldManiphestTaskAdapter'
  AND c.fieldName = 'author';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'maniphest.task.assignee'
  WHERE r.contentType = 'HeraldManiphestTaskAdapter'
  AND c.fieldName = 'assignee';
