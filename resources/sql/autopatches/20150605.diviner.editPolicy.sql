ALTER TABLE {$NAMESPACE}_diviner.diviner_livebook
  ADD COLUMN editPolicy VARBINARY(64) NOT NULL AFTER viewPolicy;

UPDATE {$NAMESPACE}_diviner.diviner_livebook
  SET editPolicy = 'admin'
  WHERE editPolicy = '';
