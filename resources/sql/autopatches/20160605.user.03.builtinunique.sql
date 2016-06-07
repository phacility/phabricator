ALTER TABLE {$NAMESPACE}_user.user_preferences
  ADD UNIQUE KEY `key_builtin` (builtinKey);
