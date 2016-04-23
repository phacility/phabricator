<?php

final class PhabricatorDaemonsSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_IMPORTANT;
  }

  protected function executeChecks() {

    $task_daemon = id(new PhabricatorDaemonLogQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withStatus(PhabricatorDaemonLogQuery::STATUS_RUNNING)
      ->withDaemonClasses(array('PhabricatorTaskmasterDaemon'))
      ->setLimit(1)
      ->execute();

    if (!$task_daemon) {
      $doc_href = PhabricatorEnv::getDocLink('Managing Daemons with phd');

      $summary = pht(
        'You must start the Phabricator daemons to send email, rebuild '.
        'search indexes, and do other background processing.');

      $message = pht(
        'The Phabricator daemons are not running, so Phabricator will not '.
        'be able to perform background processing (including sending email, '.
        'rebuilding search indexes, importing commits, cleaning up old data, '.
        'and running builds).'.
        "\n\n".
        'Use %s to start daemons. See %s for more information.',
        phutil_tag('tt', array(), 'bin/phd start'),
        phutil_tag(
          'a',
          array(
            'href' => $doc_href,
            'target' => '_blank',
          ),
          pht('Managing Daemons with phd')));

      $this->newIssue('daemons.not-running')
        ->setShortName(pht('Daemons Not Running'))
        ->setName(pht('Phabricator Daemons Are Not Running'))
        ->setSummary($summary)
        ->setMessage($message)
        ->addCommand('phabricator/ $ ./bin/phd start');
    }

    $expect_user = PhabricatorEnv::getEnvConfig('phd.user');
    if (strlen($expect_user)) {
      $all_daemons = id(new PhabricatorDaemonLogQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withStatus(PhabricatorDaemonLogQuery::STATUS_ALIVE)
        ->execute();
      foreach ($all_daemons as $daemon) {
        $actual_user = $daemon->getRunningAsUser();
        if ($actual_user == $expect_user) {
          continue;
        }

        $summary = pht(
          'At least one daemon is currently running as the wrong user.');

        $message = pht(
          'A daemon is running as user %s, but daemons should be '.
          'running as %s.'.
          "\n\n".
          'Either adjust the configuration setting %s or restart the '.
          'daemons. Daemons should attempt to run as the proper user when '.
          'restarted.',
          phutil_tag('tt', array(), $actual_user),
          phutil_tag('tt', array(), $expect_user),
          phutil_tag('tt', array(), 'phd.user'));

        $this->newIssue('daemons.run-as-different-user')
          ->setName(pht('Daemon Running as Wrong User'))
          ->setSummary($summary)
          ->setMessage($message)
          ->addPhabricatorConfig('phd.user')
          ->addCommand('phabricator/ $ ./bin/phd restart');

        break;
      }
    }
  }

}
