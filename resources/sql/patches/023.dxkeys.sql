ALTER TABLE {$NAMESPACE}_differential.differential_changeset
  ADD KEY (diffID);

ALTER TABLE {$NAMESPACE}_differential.differential_comment
  ADD KEY (revisionID);

ALTER TABLE {$NAMESPACE}_differential.differential_diff
  ADD KEY (revisionID);

ALTER TABLE {$NAMESPACE}_differential.differential_inlinecomment
  ADD KEY (changesetID);

ALTER TABLE {$NAMESPACE}_differential.differential_inlinecomment
  ADD KEY (commentID);

ALTER TABLE {$NAMESPACE}_differential.differential_hunk
  ADD KEY (changesetID);

ALTER TABLE {$NAMESPACE}_herald.herald_transcript
  ADD KEY (objectPHID);

ALTER TABLE {$NAMESPACE}_differential.differential_revision
  ADD KEY (authorPHID, status);

ALTER TABLE {$NAMESPACE}_differential.differential_revision
  ADD UNIQUE KEY (phid);

ALTER TABLE {$NAMESPACE}_metamta.metamta_mailinglist
  ADD UNIQUE KEY (phid);
