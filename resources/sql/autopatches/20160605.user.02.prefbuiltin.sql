ALTER TABLE {$NAMESPACE}_user.user_preferences
  ADD builtinKey VARCHAR(32) COLLATE {$COLLATE_TEXT};
