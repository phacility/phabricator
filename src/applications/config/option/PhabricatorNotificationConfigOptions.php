<?php

final class PhabricatorNotificationConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Notifications');
  }

  public function getDescription() {
    return pht('Configure real-time notifications.');
  }

  public function getIcon() {
    return 'fa-bell';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    $servers_type = 'cluster.notifications';
    $servers_help = $this->deformat(pht(<<<EOTEXT
Provide a list of notification servers to enable real-time notifications.

For help setting up notification servers, see **[[ %s | %s ]]** in the
documentation.
EOTEXT
      ,
      PhabricatorEnv::getDoclink(
        'Notifications User Guide: Setup and Configuration'),
      pht('Notifications User Guide: Setup and Configuration')));

    $servers_example1 = array(
      array(
        'type' => 'client',
        'host' => 'phabricator.mycompany.com',
        'port' => 22280,
        'protocol' => 'https',
      ),
      array(
        'type' => 'admin',
        'host' => '127.0.0.1',
        'port' => 22281,
        'protocol' => 'http',
      ),
    );

    $servers_example1 = id(new PhutilJSON())->encodeAsList(
      $servers_example1);

    return array(
      $this->newOption('notification.servers', $servers_type, array())
        ->setSummary(pht('Configure real-time notifications.'))
        ->setDescription($servers_help)
        ->addExample(
          $servers_example1,
          pht('Simple Example')),
    );
  }

}
