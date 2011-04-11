ALTER TABLE phabricator_differential.differential_diffproperty
  ADD UNIQUE KEY (diffID, name);

