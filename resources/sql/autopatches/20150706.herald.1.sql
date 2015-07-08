UPDATE {$NAMESPACE}_herald.herald_condition
    SET fieldName = 'diffusion.commit.autoclose'
  WHERE fieldName = 'repository-autoclose-branch';

UPDATE {$NAMESPACE}_herald.herald_condition
    SET fieldName = 'diffusion.commit.package.audit'
  WHERE fieldName = 'need-audit-for-package';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.affected'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'diff-file';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.author'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'author';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.branches'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'branches';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.committer'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'committer';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.diff.new'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'diff-added-content';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.diff'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'diff-content';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.diff.old'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'diff-removed-content';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.enormous'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'diff-enormous';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.message'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'body';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.package'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'affected-package';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.package.owners'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'affected-package-owner';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.repository'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'repository';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.repository.projects'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'repository-projects';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.reviewer'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'reviewer';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.revision.accepted'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'differential-accepted';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.revision'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'differential-revision';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.revision.subscribers'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'differential-ccs';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.commit.revision.reviewers'
  WHERE r.contentType = 'commit'
  AND c.fieldName = 'differential-reviewers';
