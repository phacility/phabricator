UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.commit.message'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'body';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.commit.author'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'author';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.commit.author.raw'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'author-raw';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.commit.committer'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'committer';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.commit.committer.raw'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'committer-raw';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.commit.branches'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'branches';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.content.pusher'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'pusher';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.content.pusher.projects'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'pusher-projects';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.content.repository'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'repository';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.content.repository.projects'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'repository-projects';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.content.pusher.is-committer'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'pusher-is-committer';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.content.revision'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'differential-revision';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.content.revision.accepted'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'differential-accepted';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.content.revision.reviewers'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'differential-reviewers';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.content.revision.subscribers'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'differential-ccs';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.content.diff.enormous'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'diff-enormous';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.commit.affected'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'diff-file';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.commit.diff.content'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'diff-content';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.commit.diff.new'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'diff-added-content';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.commit.diff.old'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'diff-removed-content';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.content.merge'
  WHERE r.contentType = 'HeraldPreCommitContentAdapter'
  AND c.fieldName = 'is-merge-commit';
