ALTER TABLE `phabricator_project`.`project`
  MODIFY `phrictionSlug` varchar(128) binary;

ALTER TABLE phabricator_repository.repository_path
  ADD COLUMN pathHash varchar(32) binary AFTER path;
UPDATE phabricator_repository.repository_path SET pathHash = MD5(path);
ALTER TABLE phabricator_repository.repository_path
  MODIFY pathHash varchar(32) binary not null,
  DROP KEY path,
  ADD UNIQUE KEY (pathHash);

ALTER TABLE phabricator_user.user_sshkey
  ADD COLUMN keyHash varchar(32) binary AFTER keyBody;
UPDATE phabricator_user.user_sshkey SET keyHash = MD5(keyBody);
ALTER TABLE phabricator_user.user_sshkey
  MODIFY keyHash varchar(32) binary not null,
  DROP KEY keyBody,
  ADD UNIQUE KEY (keyHash);
