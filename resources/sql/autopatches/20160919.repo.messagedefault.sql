ALTER TABLE {$NAMESPACE}_repository.repository_statusmessage
  CHANGE messageCount messageCount INT UNSIGNED NOT NULL DEFAULT 0;
