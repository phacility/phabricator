ALTER TABLE {$NAMESPACE}_conpherence.conpherence_thread
  ADD recentParticipantPHIDs LONGTEXT NOT NULL COLLATE utf8_bin AFTER title,
  ADD messageCount BIGINT UNSIGNED NOT NULL AFTER title;

ALTER TABLE {$NAMESPACE}_conpherence.conpherence_participant
  ADD seenMessageCount BIGINT UNSIGNED NOT NULL AFTER behindTransactionPHID;
