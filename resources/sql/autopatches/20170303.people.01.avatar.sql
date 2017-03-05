ALTER TABLE {$NAMESPACE}_user.user
  ADD defaultProfileImagePHID VARBINARY(64);

ALTER TABLE {$NAMESPACE}_user.user
  ADD defaultProfileImageVersion VARCHAR(64) COLLATE {$COLLATE_TEXT};
