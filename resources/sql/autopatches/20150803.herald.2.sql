UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'diffusion.block'
  WHERE r.contentType != 'differential.diff'
  AND a.action = 'block';

UPDATE {$NAMESPACE}_herald.herald_action a
  JOIN {$NAMESPACE}_herald.herald_rule r
  ON a.ruleID = r.id
  SET a.action = 'differential.block'
  WHERE r.contentType = 'differential.diff'
  AND a.action = 'block';
