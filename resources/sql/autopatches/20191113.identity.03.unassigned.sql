UPDATE {$NAMESPACE}_repository.repository_identity
  SET currentEffectiveUserPHID = NULL
  WHERE currentEffectiveUserPHID = 'unassigned()';
