ALTER TABLE phabricator_differential.differential_inlinecomment
  ADD KEY (revisionID, authorPHID);