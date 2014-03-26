ALTER TABLE {$NAMESPACE}_repository.repository_pushlog
  DROP remoteAddress;

ALTER TABLE {$NAMESPACE}_repository.repository_pushlog
  DROP remoteProtocol;

ALTER TABLE {$NAMESPACE}_repository.repository_pushlog
  DROP transactionKey;

ALTER TABLE {$NAMESPACE}_repository.repository_pushlog
  DROP rejectCode;

ALTER TABLE {$NAMESPACE}_repository.repository_pushlog
  DROP rejectDetails;
