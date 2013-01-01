<?php

final class PhabricatorSetupCheckExtraConfig extends PhabricatorSetupCheck {

  protected function executeChecks() {
    $all_keys = PhabricatorEnv::getAllConfigKeys();
    $all_keys = array_keys($all_keys);
    sort($all_keys);

    $defined_keys = PhabricatorApplicationConfigOptions::loadAllOptions();

    foreach ($all_keys as $key) {
      if (isset($defined_keys[$key])) {
        continue;
      }
      $summary = pht("This option is not recognized. It may be misspelled.");
      $message = pht(
        "The configuration option '%s' is not recognized. It may be ".
        "misspelled, or it might have existed in an older version of ".
        "Phabricator. It has no effect, and should be corrected or deleted.",
        $key);

      $this
        ->newIssue('config.unknown.'.$key)
        ->setShortName(pht('Unknown Config'))
        ->setName(pht('Unknown Configuration Option "%s"', $key))
        ->setSummary($summary)
        ->setMessage($message)
        ->addPhabricatorConfig($key);
    }
  }
}
