ALTER TABLE {$NAMESPACE}_repository.repository_refcursor
  ADD phid VARBINARY(64) NOT NULL AFTER id;
