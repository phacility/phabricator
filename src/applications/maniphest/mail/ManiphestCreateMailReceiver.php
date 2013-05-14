<?php

final class ManiphestCreateMailReceiver extends PhabricatorMailReceiver {

  public function isEnabled() {
    $app_class = 'PhabricatorApplicationManiphest';
    return PhabricatorApplication::isClassInstalled($app_class);
  }

  public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail) {
    $config_key = 'metamta.maniphest.public-create-email';
    $create_address = PhabricatorEnv::getEnvConfig($config_key);

    foreach ($mail->getToAddresses() as $to_address) {
      if ($this->matchAddresses($create_address, $to_address)) {
        return true;
      }
    }

    return false;
  }

}
