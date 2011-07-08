ALTER TABLE phabricator_file.file
  ADD COLUMN authorPHID VARCHAR(64) BINARY,
  ADD KEY (authorPHID);