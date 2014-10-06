TRUNCATE TABLE {$NAMESPACE}_fund.fund_backer;

ALTER TABLE {$NAMESPACE}_fund.fund_backer
  CHANGE amountInCents amountAsCurrency VARCHAR(64) NOT NULL COLLATE utf8_bin;
