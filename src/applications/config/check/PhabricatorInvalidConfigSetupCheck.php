<?php

final class PhabricatorInvalidConfigSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

  protected function executeChecks() {
    $groups = PhabricatorApplicationConfigOptions::loadAll();
    foreach ($groups as $group) {
      $options = $group->getOptions();
      foreach ($options as $option) {
        try {
          $group->validateOption(
            $option,
            PhabricatorEnv::getUnrepairedEnvConfig($option->getKey()));
        } catch (PhabricatorConfigValidationException $ex) {
          $this
            ->newIssue('config.invalid.'.$option->getKey())
            ->setName(pht("Config '%s' Invalid", $option->getKey()))
            ->setMessage(
              pht(
                "Configuration option '%s' has invalid value and ".
                "was restored to the default: %s",
                $option->getKey(),
                $ex->getMessage()))
            ->addPhabricatorConfig($option->getKey());
        }
      }
    }
  }

}
