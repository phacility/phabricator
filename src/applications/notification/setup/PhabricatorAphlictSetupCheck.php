<?php

final class PhabricatorAphlictSetupCheck extends PhabricatorSetupCheck {

  protected function executeChecks() {
    try {
      PhabricatorNotificationClient::tryAnyConnection();
    } catch (Exception $ex) {
      $message = pht(
        "Phabricator is configured to use a notification server, but is ".
        "unable to connect to it. You should resolve this issue or disable ".
        "the notification server. It may be helpful to double check your ".
        "configuration or restart the server using the command below.\n\n%s",
        phutil_tag(
          'pre',
          array(),
          array(
            get_class($ex),
            "\n",
            $ex->getMessage(),
          )));


      $this->newIssue('aphlict.connect')
        ->setShortName(pht('Notification Server Down'))
        ->setName(pht('Unable to Connect to Notification Server'))
        ->setMessage($message)
        ->addRelatedPhabricatorConfig('notification.servers')
        ->addCommand(
          pht(
            "(To start the server, run this command.)\n%s",
            'phabricator/ $ ./bin/aphlict start'));

      return;
    }
  }
}
