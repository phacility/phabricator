<?php

final class PhabricatorMailSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

  protected function executeChecks() {
    if (PhabricatorEnv::getEnvConfig('cluster.mailers')) {
      return;
    }

    $message = pht(
      'You haven\'t configured mailers yet, so this server won\'t be able '.
      'to send outbound mail or receive inbound mail. See the '.
      'configuration setting "cluster.mailers" for details.');

    $this->newIssue('cluster.mailers')
      ->setName(pht('Mailers Not Configured'))
      ->setMessage($message)
      ->addPhabricatorConfig('cluster.mailers');
  }
}
