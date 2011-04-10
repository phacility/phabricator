ALTER TABLE phabricator_differential.differential_changeset
  ADD KEY (diffID);

ALTER TABLE phabricator_differential.differential_comment
  ADD KEY (revisionID);

ALTER TABLE phabricator_differential.differential_diff
  ADD KEY (revisionID);

ALTER TABLE phabricator_differential.differential_inlinecomment
  ADD KEY (changesetID);

ALTER TABLE phabricator_differential.differential_inlinecomment
  ADD KEY (commentID);

ALTER TABLE phabricator_differential.differential_hunk
  ADD KEY (changesetID);

ALTER TABLE phabricator_herald.herald_transcript
  ADD KEY (objectPHID);

ALTER TABLE phabricator_differential.differential_revision
  ADD KEY (authorPHID, status);

ALTER TABLE phabricator_differential.differential_revision
  ADD UNIQUE KEY (phid);

ALTER TABLE phabricator_metamta.metamta_mailinglist
  ADD UNIQUE KEY (phid);
