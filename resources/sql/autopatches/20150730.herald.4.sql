UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'maniphest.assign.other'
  WHERE r.ruleType != 'personal'
  AND a.action = 'assigntask';

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'maniphest.assign.self'
  WHERE r.ruleType = 'personal'
  AND a.action = 'assigntask';
