ALTER TABLE {$NAMESPACE}_conpherence.conpherence_participant
  ADD settings LONGTEXT NOT NULL COLLATE utf8_bin AFTER behindTransactionPHID;
