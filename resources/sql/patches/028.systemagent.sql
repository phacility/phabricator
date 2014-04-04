ALTER TABLE {$NAMESPACE}_user.user
  ADD isSystemAgent bool not null default 0;
