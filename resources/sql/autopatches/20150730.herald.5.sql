UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'differential.reviewers.blocking'
  WHERE r.ruleType != 'personal'
  AND a.action = 'addreviewers';

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'differential.reviewers.self.blocking'
  WHERE r.ruleType = 'personal'
  AND a.action = 'addreviewers';

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'differential.reviewers.add'
  WHERE r.ruleType != 'personal'
  AND a.action = 'addblockingreviewers';

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'differential.reviewers.self.add'
  WHERE r.ruleType = 'personal'
  AND a.action = 'addblockingreviewers';
