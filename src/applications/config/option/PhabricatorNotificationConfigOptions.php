<?php

final class PhabricatorNotificationConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Notifications");
  }

  public function getDescription() {
    return pht("Configure real-time notifications.");
  }

  public function getOptions() {
    return array(
      $this->newOption('notification.enabled', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Enable Real-Time Notifications"),
            pht("Disable Real-Time Notifications"),
          ))
        ->setSummary(pht('Enable real-time notifications.'))
        ->setDescription(
          pht(
            "Enable real-time notifications. You must also run a Node.js ".
            "based notification server for this to work. Consult the ".
            "documentation in 'Notifications User Guide: Setup and ".
            "Configuration' for instructions.")),
      $this->newOption(
        'notification.client-uri',
        'string',
        'http://localhost:22280/')
        ->setDescription(pht('Location of the client server.')),
      $this->newOption(
        'notification.server-uri',
        'string',
        'http://localhost:22281/')
        ->setDescription(pht('Location of the notification receiver server.')),
      $this->newOption('notification.user', 'string', null)
        ->setSummary(pht('Drop permissions to a less-privileged user.'))
        ->setDescription(
          pht(
            "The notifcation server must be started as root so it can bind ".
            "to privileged ports, but if you specify a system user here it ".
            "will drop permissions to that user after binding to the ports ".
            "it needs.")),
      $this->newOption('notification.log', 'string', '/var/log/aphlict.log')
        ->setDescription(pht('Location of the server log file.')),
      $this->newOption(
        'notification.pidfile',
        'string',
        '/var/run/aphlict.pid')
        ->setDescription(pht('Location of the server PID file.')),
      $this->newOption('notification.debug', 'bool', false)
        ->setDescription(pht('Enable debug output in the browser.')),
    );
  }

}
