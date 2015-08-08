UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'projects.add'
  WHERE a.action = 'addprojects';

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'projects.remove'
  WHERE a.action = 'removeprojects';
