ALTER TABLE {$NAMESPACE}_user.phabricator_session
  ADD signedLegalpadDocuments BOOL NOT NULL DEFAULT 0;

ALTER TABLE {$NAMESPACE}_legalpad.legalpad_document
  ADD requireSignature BOOL NOT NULL DEFAULT 0;
