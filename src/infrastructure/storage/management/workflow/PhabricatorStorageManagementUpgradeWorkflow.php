<?php

final class PhabricatorStorageManagementUpgradeWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('upgrade')
      ->setExamples('**upgrade** [__options__]')
      ->setSynopsis(pht('Upgrade database schemata.'))
      ->setArguments(
        array(
          array(
            'name'  => 'apply',
            'param' => 'patch',
            'help'  => pht(
              'Apply __patch__ explicitly. This is an advanced feature for '.
              'development and debugging; you should not normally use this '.
              'flag. This skips adjustment.'),
          ),
          array(
            'name'  => 'no-quickstart',
            'help'  => pht(
              'Build storage patch-by-patch from scatch, even if it could '.
              'be loaded from the quickstart template.'),
          ),
          array(
            'name'  => 'init-only',
            'help'  => pht(
              'Initialize storage only; do not apply patches or adjustments.'),
          ),
          array(
            'name' => 'no-adjust',
            'help' => pht(
              'Do not apply storage adjustments after storage upgrades.'),
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
        pht(
          'Before running storage upgrades, you should take down the '.
          'Phabricator web interface and stop any running Phabricator '.
          'daemons (you can disable this warning with %s).',
          '--force'));

      if (!phutil_console_confirm(pht('Are you ready to continue?'))) {
        echo pht('Cancelled.')."\n";
        return 1;
      }
    }

    $apply_only = $args->getArg('apply');
    if ($apply_only) {
      if (empty($patches[$apply_only])) {
        throw new PhutilArgumentUsageException(
          pht(
            "%s argument '%s' is not a valid patch. ".
            "Use '%s' to show patch status.",
            '--apply',
            $apply_only,
            'storage status'));
      }
    }

    $no_quickstart = $args->getArg('no-quickstart');
    $init_only = $args->getArg('init-only');
    $no_adjust = $args->getArg('no-adjust');

    $applied = $api->getAppliedPatches();
    if ($applied === null) {

      if ($is_dry) {
        echo pht(
          "DRYRUN: Patch metadata storage doesn't exist yet, ".
          "it would be created.\n");
        return 0;
      }

      if ($apply_only) {
        throw new PhutilArgumentUsageException(
          pht(
            'Storage has not been initialized yet, you must initialize '.
            'storage before selectively applying patches.'));
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
        echo pht('Loading quickstart template...')."\n";
        $root = dirname(phutil_get_library_root('phabricator'));
        $sql  = $root.'/resources/sql/quickstart.sql';
        $api->applyPatchSQL($sql);
      }
    }

    if ($init_only) {
      echo pht('Storage initialized.')."\n";
      return 0;
    }

    $applied = $api->getAppliedPatches();
    $applied = array_fuse($applied);

    $skip_mark = false;
    if ($apply_only) {
      if (isset($applied[$apply_only])) {

        unset($applied[$apply_only]);
        $skip_mark = true;

        if (!$is_force && !$is_dry) {
          echo phutil_console_wrap(
            pht(
              "Patch '%s' has already been applied. Are you sure you want ".
              "to apply it again? This may put your storage in a state ".
              "that the upgrade scripts can not automatically manage.",
              $apply_only));
          if (!phutil_console_confirm(pht('Apply patch again?'))) {
            echo pht('Cancelled.')."\n";
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
              echo pht(
                "Unable to apply patch '%s' because it depends ".
                "on patch '%s', which has not been applied.\n",
                $apply_only,
                $after);
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
          echo pht("DRYRUN: Would apply patch '%s'.", $key)."\n";
        } else {
          echo pht("Applying patch '%s'...", $key)."\n";
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
            pht(
              'Some patches could not be applied: %s',
              implode(', ', array_keys($patches))));
        } else if (!$is_dry && !$apply_only) {
          echo pht(
            "Storage is up to date. Use '%s' for details.",
            'storage status')."\n";
        }
        break;
      }
    }

    $console = PhutilConsole::getConsole();
    if ($no_adjust || $init_only || $apply_only) {
      $console->writeOut(
        "%s\n",
        pht('Declining to apply storage adjustments.'));
      return 0;
    } else {
      return $this->adjustSchemata($is_force, $unsafe = false, $is_dry);
    }
  }

}
