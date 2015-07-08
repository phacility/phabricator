UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.ref.type'
  WHERE r.contentType = 'HeraldPreCommitRefAdapter'
  AND c.fieldName = 'ref-type';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.ref.name'
  WHERE r.contentType = 'HeraldPreCommitRefAdapter'
  AND c.fieldName = 'ref-name';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.ref.change'
  WHERE r.contentType = 'HeraldPreCommitRefAdapter'
  AND c.fieldName = 'ref-change';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.ref.repository'
  WHERE r.contentType = 'HeraldPreCommitRefAdapter'
  AND c.fieldName = 'repository';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.ref.repository.projects'
  WHERE r.contentType = 'HeraldPreCommitRefAdapter'
  AND c.fieldName = 'repository-projects';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.ref.pusher'
  WHERE r.contentType = 'HeraldPreCommitRefAdapter'
  AND c.fieldName = 'pusher';

UPDATE {$NAMESPACE}_herald.herald_condition c
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON c.ruleID = r.id
  SET c.fieldName = 'diffusion.pre.ref.pusher.projects'
  WHERE r.contentType = 'HeraldPreCommitRefAdapter'
  AND c.fieldName = 'pusher-projects';
