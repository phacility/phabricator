UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.revision.diff.affected'
  WHERE r.contentType = 'differential'
  AND c.fieldName = 'diff-file';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.revision.author'
  WHERE r.contentType = 'differential'
  AND c.fieldName = 'author';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.revision.author.projects'
  WHERE r.contentType = 'differential'
  AND c.fieldName = 'authorprojects';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.revision.diff.new'
  WHERE r.contentType = 'differential'
  AND c.fieldName = 'diff-added-content';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.revision.diff.content'
  WHERE r.contentType = 'differential'
  AND c.fieldName = 'diff-content';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.revision.diff.old'
  WHERE r.contentType = 'differential'
  AND c.fieldName = 'diff-removed-content';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.revision.package'
  WHERE r.contentType = 'differential'
  AND c.fieldName = 'affected-package';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.revision.repository'
  WHERE r.contentType = 'differential'
  AND c.fieldName = 'repository';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.revision.repository.projects'
  WHERE r.contentType = 'differential'
  AND c.fieldName = 'repository-projects';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.revision.reviewers'
  WHERE r.contentType = 'differential'
  AND c.fieldName = 'reviewers';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.revision.summary'
  WHERE r.contentType = 'differential'
  AND c.fieldName = 'body';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.revision.title'
  WHERE r.contentType = 'differential'
  AND c.fieldName = 'title';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'differential.revision.package.owners'
  WHERE r.contentType = 'differential'
  AND c.fieldName = 'affected-package-owner';
