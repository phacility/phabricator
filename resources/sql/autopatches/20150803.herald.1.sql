UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'diffusion.auditors.add'
  WHERE r.ruleType != 'personal'
  AND a.action = 'audit';

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'diffusion.auditors.self.add'
  WHERE r.ruleType = 'personal'
  AND a.action = 'audit';
