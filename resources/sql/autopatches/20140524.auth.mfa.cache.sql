ALTER TABLE {$NAMESPACE}_user.user
  ADD isEnrolledInMultiFactor BOOL NOT NULL DEFAULT 0;
