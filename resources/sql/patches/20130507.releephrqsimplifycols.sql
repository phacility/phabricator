ALTER TABLE {$NAMESPACE}_releeph.releeph_request
  DROP COLUMN requestCommitIdentifier,
  DROP COLUMN requestCommitOrdinal,
  DROP COLUMN status,
  DROP COLUMN committedByUserPHID,
  DROP KEY `requestIdentifierBranch`,
  ADD CONSTRAINT
    UNIQUE KEY `requestIdentifierBranch` (`requestCommitPHID`, `branchID`);
