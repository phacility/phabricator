/* The "20150730.herald.5.sql" patch incorrectly swapped blocking and
   non-blocking "Add Reviewer" rules. This swaps back any rules which
   were last modified before the patch was applied. */

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'differential.reviewers.blocking.tmp'
  WHERE a.action = 'differential.reviewers.add'
  AND r.dateModified <=
    (SELECT applied FROM {$NAMESPACE}_meta_data.patch_status
      WHERE patch = 'phabricator:20150730.herald.5.sql');

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'differential.reviewers.add'
  WHERE a.action = 'differential.reviewers.blocking'
  AND r.dateModified <=
    (SELECT applied FROM {$NAMESPACE}_meta_data.patch_status
      WHERE patch = 'phabricator:20150730.herald.5.sql');

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'differential.reviewers.blocking'
  WHERE a.action = 'differential.reviewers.blocking.tmp';


UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'differential.reviewers.self.blocking.tmp'
  WHERE a.action = 'differential.reviewers.self.add'
  AND r.dateModified <=
    (SELECT applied FROM {$NAMESPACE}_meta_data.patch_status
      WHERE patch = 'phabricator:20150730.herald.5.sql');

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'differential.reviewers.self.add'
  WHERE a.action = 'differential.reviewers.self.blocking'
  AND r.dateModified <=
    (SELECT applied FROM {$NAMESPACE}_meta_data.patch_status
      WHERE patch = 'phabricator:20150730.herald.5.sql');

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'differential.reviewers.self.blocking'
  WHERE a.action = 'differential.reviewers.self.blocking.tmp';
