ALTER TABLE {$NAMESPACE}_herald.herald_rule ADD ruleType varchar(255) not null DEFAULT 'global';
CREATE INDEX IDX_RULE_TYPE on {$NAMESPACE}_herald.herald_rule (ruleType(128));
