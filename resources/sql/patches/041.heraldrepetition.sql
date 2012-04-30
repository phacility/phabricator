CREATE TABLE {$NAMESPACE}_herald.herald_ruleapplied (
  ruleID int unsigned not null,
  phid varchar(64) binary not null,
  PRIMARY KEY(ruleID, phid)
) ENGINE=InnoDB;

ALTER TABLE {$NAMESPACE}_herald.herald_rule add repetitionPolicy int unsigned;
