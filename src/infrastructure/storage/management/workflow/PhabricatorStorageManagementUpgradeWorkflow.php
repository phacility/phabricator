<?php

final class PhabricatorStorageManagementUpgradeWorkflow
  extends PhabricatorStorageManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('upgrade')
      ->setExamples('**upgrade** [__options__]')
      ->setSynopsis("Upgrade database schemata.")
      ->setArguments(
        array(
          array(
            'name'  => 'apply',
            'param' => 'patch',
            'help'  => 'Apply __patch__ explicitly. This is an advanced '.
                       'feature for development and debugging; you should '.
                       'not normally use this flag.',
          ),
          array(
            'name'  => 'no-quickstart',
            'help'  => 'Build storage patch-by-patch from scatch, even if it '.
                       'could be loaded from the quickstart template.',
          ),
          array(
            'name'  => 'init-only',
            'help'  => 'Initialize storage only; do not apply patches.',
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $is_dry = $args->getArg('dryrun');
    $is_force = $args->getArg('force');

    $api = $this->getAPI();
    $patches = $this->getPatches();

    if (!$is_dry && !$is_force) {
      echo phutil_console_wrap(
        "Before running storage upgrades, you should take down the ".
        "Phabricator web interface and stop any running Phabricator ".
        "daemons (you can disable this warning with --force).");

      if (!phutil_console_confirm('Are you ready to continue?')) {
        echo "Cancelled.\n";
        return 1;
      }
    }

    $apply_only = $args->getArg('apply');
    if ($apply_only) {
      if (empty($patches[$apply_only])) {
        throw new PhutilArgumentUsageException(
          "--apply argument '{$apply_only}' is not a valid patch. Use ".
          "'storage status' to show patch status.");
      }
    }

    $no_quickstart = $args->getArg('no-quickstart');
    $init_only = $args->getArg('init-only');

    $applied = $api->getAppliedPatches();
    if ($applied === null) {

      if ($is_dry) {
        echo "DRYRUN: Patch metadata storage doesn't exist yet, it would ".
             "be created.\n";
        return 0;
      }

      if ($apply_only) {
        throw new PhutilArgumentUsageException(
          "Storage has not been initialized yet, you must initialize storage ".
          "before selectively applying patches.");
        return 1;
      }

      $legacy = $api->getLegacyPatches($patches);
      if ($legacy || $no_quickstart || $init_only) {

        // If we have legacy patches, we can't quickstart.

        $api->createDatabase('meta_data');
        $api->createTable(
          'meta_data',
          'patch_status',
          array(
            'patch VARCHAR(255) NOT NULL PRIMARY KEY COLLATE utf8_general_ci',
            'applied INT UNSIGNED NOT NULL',
          ));

        foreach ($legacy as $patch) {
          $api->markPatchApplied($patch);
        }
      } else {
        echo "Loading quickstart template...\n";
        $root = dirname(phutil_get_library_root('phabricator'));
        $sql  = $root.'/resources/sql/quickstart.sql';
        $api->applyPatchSQL($sql);
      }
    }

    if ($init_only) {
      echo "Storage initialized.\n";
      return 0;
    }

    $applied = $api->getAppliedPatches();
    $applied = array_fill_keys($applied, true);

    $skip_mark = false;
    if ($apply_only) {
      if (isset($applied[$apply_only])) {

        unset($applied[$apply_only]);
        $skip_mark = true;

        if (!$is_force && !$is_dry) {
          echo phutil_console_wrap(
            "Patch '{$apply_only}' has already been applied. Are you sure ".
            "you want to apply it again? This may put your storage in a state ".
            "that the upgrade scripts can not automatically manage.");
          if (!phutil_console_confirm('Apply patch again?')) {
            echo "Cancelled.\n";
            return 1;
          }
        }
      }
    }

    while (true) {
      $applied_something = false;
      foreach ($patches as $key => $patch) {
        if (isset($applied[$key])) {
          unset($patches[$key]);
          continue;
        }

        if ($apply_only && $apply_only != $key) {
          unset($patches[$key]);
          continue;
        }

        $can_apply = true;
        foreach ($patch->getAfter() as $after) {
          if (empty($applied[$after])) {
            if ($apply_only) {
              echo "Unable to apply patch '{$apply_only}' because it depends ".
                   "on patch '{$after}', which has not been applied.\n";
              return 1;
            }
            $can_apply = false;
            break;
          }
        }

        if (!$can_apply) {
          continue;
        }

        $applied_something = true;

        if ($is_dry) {
          echo "DRYRUN: Would apply patch '{$key}'.\n";
        } else {
          echo "Applying patch '{$key}'...\n";
          $api->applyPatch($patch);
          if (!$skip_mark) {
            $api->markPatchApplied($key);
          }
        }

        unset($patches[$key]);
        $applied[$key] = true;
      }

      if (!$applied_something) {
        if (count($patches)) {
          throw new Exception(
            "Some patches could not be applied: ".
            implode(', ', array_keys($patches)));
        } else if (!$is_dry && !$apply_only) {
          echo "Storage is up to date. Use 'storage status' for details.\n";
        }
        break;
      }
    }

    return 0;
  }

}
