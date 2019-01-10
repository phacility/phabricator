<?php

abstract class PhabricatorApplicationMailReceiver
  extends PhabricatorMailReceiver {

  abstract protected function newApplication();

  final public function isEnabled() {
    return $this->newApplication()->isInstalled();
  }

  final public function canAcceptMail(PhabricatorMetaMTAReceivedMail $mail) {
    $application = $this->newApplication();
    $viewer = $this->getViewer();

    $application_emails = id(new PhabricatorMetaMTAApplicationEmailQuery())
      ->setViewer($viewer)
      ->withApplicationPHIDs(array($application->getPHID()))
      ->execute();

    foreach ($mail->newTargetAddresses() as $address) {
      foreach ($application_emails as $application_email) {
        $create_address = $application_email->newAddress();
        if (PhabricatorMailUtil::matchAddresses($create_address, $address)) {
          $this->setApplicationEmail($application_email);
          return true;
        }
      }
    }

    return false;
  }

}
