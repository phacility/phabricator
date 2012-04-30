ALTER TABLE {$NAMESPACE}_herald.herald_ruleedit
  ADD ruleName VARCHAR(255) NOT NULL COLLATE utf8_general_ci;

ALTER TABLE {$NAMESPACE}_herald.herald_ruleedit
  ADD action VARCHAR(32) NOT NULL COLLATE utf8_general_ci;
