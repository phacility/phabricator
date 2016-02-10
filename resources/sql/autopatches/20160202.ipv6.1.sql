ALTER TABLE {$NAMESPACE}_repository.repository_pullevent
  CHANGE remoteAddress remoteAddress VARBINARY(64);

ALTER TABLE {$NAMESPACE}_repository.repository_pushevent
  CHANGE remoteAddress remoteAddress VARBINARY(64);
