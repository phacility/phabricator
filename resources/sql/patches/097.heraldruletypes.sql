ALTER TABLE phabricator_herald.herald_rule ADD ruleType varchar(255) not null DEFAULT 'global';
CREATE INDEX IDX_RULE_TYPE on phabricator_herald.herald_rule (ruleType);