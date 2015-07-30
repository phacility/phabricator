UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'subscribers.add'
  WHERE r.ruleType != 'personal'
  AND a.action = 'addcc';

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'subscribers.self.add'
  WHERE r.ruleType = 'personal'
  AND a.action = 'addcc';

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'subscribers.remove'
  WHERE r.ruleType != 'personal'
  AND a.action = 'remcc';

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'subscribers.self.remove'
  WHERE r.ruleType = 'personal'
  AND a.action = 'remcc';
