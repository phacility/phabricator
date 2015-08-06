UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'legalpad.require'
  WHERE r.ruleType != 'personal'
  AND a.action = 'signature';
