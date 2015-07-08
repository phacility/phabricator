ALTER TABLE {$NAMESPACE}_metamta.metamta_mail
  ADD phid VARBINARY(64) NOT NULL AFTER id;
