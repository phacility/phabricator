UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'email.other'
  WHERE r.ruleType != 'personal'
  AND a.action = 'email';

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'email.self'
  WHERE r.ruleType = 'personal'
  AND a.action = 'email';
