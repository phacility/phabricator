ALTER TABLE {$NAMESPACE}_fund.fund_initiative
  ADD totalAsCurrency VARCHAR(64) NOT NULL COLLATE utf8_bin;

UPDATE {$NAMESPACE}_fund.fund_initiative SET totalAsCurrency = '0.00 USD';
