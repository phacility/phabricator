ALTER TABLE phabricator_file.file
  ADD contentHash varchar(40) COLLATE utf8_bin;

ALTER TABLE phabricator_file.file
  ADD KEY (contentHash);
