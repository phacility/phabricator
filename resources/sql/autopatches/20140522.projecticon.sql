ALTER TABLE {$NAMESPACE}_project.project
  ADD COLUMN icon VARCHAR(32) NOT NULL COLLATE utf8_bin;

UPDATE {$NAMESPACE}_project.project
  SET icon = "fa-briefcase" WHERE icon = "";
