ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_build
  ADD COLUMN `buildGeneration` INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE {$NAMESPACE}_harbormaster.harbormaster_buildtarget
  ADD COLUMN `buildGeneration` INT UNSIGNED NOT NULL DEFAULT 0;
