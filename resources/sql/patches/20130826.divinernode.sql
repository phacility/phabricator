ALTER TABLE {$NAMESPACE}_diviner.diviner_livesymbol
  ADD nodeHash VARCHAR(64) COLLATE utf8_bin;

ALTER TABLE {$NAMESPACE}_diviner.diviner_livesymbol
  ADD UNIQUE KEY (nodeHash);
