CREATE TABLE {$NAMESPACE}_conpherence.conpherence_index (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  threadPHID VARBINARY(64) NOT NULL,
  transactionPHID VARBINARY(64) NOT NULL,
  previousTransactionPHID VARBINARY(64),
  corpus longtext
    CHARACTER SET {$CHARSET_FULLTEXT}
    COLLATE {$COLLATE_FULLTEXT}
    NOT NULL,
  KEY `key_thread` (threadPHID),
  UNIQUE KEY `key_transaction` (transactionPHID),
  UNIQUE KEY `key_previous` (previousTransactionPHID),
  FULLTEXT KEY `key_corpus` (corpus)
) ENGINE=MyISAM DEFAULT CHARSET={$CHARSET} COLLATE={$COLLATE_TEXT};
