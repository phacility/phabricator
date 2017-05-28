<?php

abstract class PhabricatorSearchManagementWorkflow
  extends PhabricatorManagementWorkflow {

  protected function validateClusterSearchConfig() {
    // Configuration is normally validated by setup self-checks on the web
    // workflow, but users may reasonsably run `bin/search` commands after
    // making manual edits to "local.json". Re-verify configuration here before
    // continuing.

    $config_key = 'cluster.search';
    $config_value = PhabricatorEnv::getEnvConfig($config_key);

    try {
      PhabricatorClusterSearchConfigOptionType::validateValue($config_value);
    } catch (Exception $ex) {
      throw new PhutilArgumentUsageException(
        pht(
          'Setting "%s" is misconfigured: %s',
          $config_key,
          $ex->getMessage()));
    }
  }

}
