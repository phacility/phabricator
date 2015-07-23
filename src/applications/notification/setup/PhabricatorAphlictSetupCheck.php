<?php

final class PhabricatorAphlictSetupCheck extends PhabricatorSetupCheck {

  protected function executeChecks() {
    $enabled = PhabricatorEnv::getEnvConfig('notification.enabled');
    if (!$enabled) {
      // Notifications aren't set up, so just ignore all of these checks.
      return;
    }

    try {
      $status = PhabricatorNotificationClient::getServerStatus();
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
        ->addRelatedPhabricatorConfig('notification.enabled')
        ->addRelatedPhabricatorConfig('notification.server-uri')
        ->addCommand(
          pht(
            "(To start the server, run this command.)\n%s",
            'phabricator/ $ ./bin/aphlict start'));

      return;
    }

    $expect_version = PhabricatorNotificationClient::EXPECT_VERSION;
    $have_version = idx($status, 'version', 1);
    if ($have_version != $expect_version) {
      $message = pht(
        'The notification server is out of date. You are running server '.
        'version %d, but Phabricator expects version %d. Restart the server '.
        'to update it, using the command below:',
        $have_version,
        $expect_version);

      $this->newIssue('aphlict.version')
        ->setShortName(pht('Notification Server Version'))
        ->setName(pht('Notification Server Out of Date'))
        ->setMessage($message)
        ->addCommand('phabricator/ $ ./bin/aphlict restart');
    }

  }
}
