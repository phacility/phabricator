<?php

final class PhabricatorSetupCheckInvalidConfig extends PhabricatorSetupCheck {

  protected function executeChecks() {
    $groups = PhabricatorApplicationConfigOptions::loadAll();
    foreach ($groups as $group) {
      $options = $group->getOptions();
      foreach ($options as $option) {
        try {
          $group->validateOption(
            $option,
            PhabricatorEnv::getEnvConfig($option->getKey()));
        } catch (PhabricatorConfigValidationException $ex) {
          $this
            ->newIssue('config.invalid.'.$option->getKey())
            ->setName(pht("Config '%s' Invalid", $option->getKey()))
            ->setMessage(
              pht(
                "Configuration option '%s' has invalid value: %s",
                $option->getKey(),
                $ex->getMessage()))
            ->addPhabricatorConfig($option->getKey());
        }
      }
    }
  }

}
