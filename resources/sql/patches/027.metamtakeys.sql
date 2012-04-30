ALTER TABLE {$NAMESPACE}_metamta.metamta_mail
  ADD KEY (status, nextRetry);

ALTER TABLE {$NAMESPACE}_metamta.metamta_mail
  ADD KEY (relatedPHID);
