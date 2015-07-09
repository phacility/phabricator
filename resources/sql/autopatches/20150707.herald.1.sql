UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.diff.affected'
  WHERE r.contentType = 'differential.diff'
  AND c.fieldName = 'diff-file';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.diff.author'
  WHERE r.contentType = 'differential.diff'
  AND c.fieldName = 'author';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.diff.author.projects'
  WHERE r.contentType = 'differential.diff'
  AND c.fieldName = 'authorprojects';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.diff.new'
  WHERE r.contentType = 'differential.diff'
  AND c.fieldName = 'diff-added-content';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.diff.content'
  WHERE r.contentType = 'differential.diff'
  AND c.fieldName = 'diff-content';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.diff.old'
  WHERE r.contentType = 'differential.diff'
  AND c.fieldName = 'diff-removed-content';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.diff.repository'
  WHERE r.contentType = 'differential.diff'
  AND c.fieldName = 'repository';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.diff.repository.projects'
  WHERE r.contentType = 'differential.diff'
  AND c.fieldName = 'repository-projects';
