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
              'Build storage patch-by-patch from scratch, even if it could '.
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

  public function didExecute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $patches = $this->getPatches();

    if (!$this->isDryRun() && !$this->isForce()) {
      $console->writeOut(
        phutil_console_wrap(
          pht(
            'Before running storage upgrades, you should take down the '.
            'Phabricator web interface and stop any running Phabricator '.
            'daemons (you can disable this warning with %s).',
            '--force')));

      if (!phutil_console_confirm(pht('Are you ready to continue?'))) {
        $console->writeOut("%s\n", pht('Cancelled.'));
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
            './bin/storage status'));
      }
    }

    $no_quickstart = $args->getArg('no-quickstart');
    $init_only     = $args->getArg('init-only');
    $no_adjust     = $args->getArg('no-adjust');

    $apis = $this->getMasterAPIs();

    $this->upgradeSchemata($apis, $apply_only, $no_quickstart, $init_only);

    if ($no_adjust || $init_only || $apply_only) {
      $console->writeOut(
        "%s\n",
        pht('Declining to apply storage adjustments.'));
    } else {
      foreach ($apis as $api) {
        $err = $this->adjustSchemata($api, false);
        if ($err) {
          return $err;
        }
      }
    }

    return 0;
  }

}
