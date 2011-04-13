ALTER TABLE phabricator_user.user
  ADD isSystemAgent bool not null default 0;