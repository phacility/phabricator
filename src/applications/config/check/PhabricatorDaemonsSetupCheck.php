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

    $phd_user = PhabricatorEnv::getEnvConfig('phd.user');
    $all_daemons = id(new PhabricatorDaemonLogQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withStatus(PhabricatorDaemonLogQuery::STATUS_ALIVE)
      ->execute();
    foreach ($all_daemons as $daemon) {

      if ($phd_user) {
        if ($daemon->getRunningAsUser() != $phd_user) {
          $doc_href = PhabricatorEnv::getDocLink('Managing Daemons with phd');

          $summary = pht(
            'At least one daemon is currently running as a different '.
            'user than configured in the Phabricator %s setting',
            'phd.user');

          $message = pht(
            'A daemon is running as user %s while the Phabricator config '.
            'specifies %s to be %s.'.
            "\n\n".
            'Either adjust %s to match %s or start '.
            'the daemons as the correct user. '.
            "\n\n".
            '%s Daemons will try to use %s to start as the configured user. '.
            'Make sure that the user who starts %s has the correct '.
            'sudo permissions to start %s daemons as %s',
            'phd.user',
            'phd.user',
            'phd',
            'sudo',
            'phd',
            'phd',
            phutil_tag('tt', array(), $daemon->getRunningAsUser()),
            phutil_tag('tt', array(), $phd_user),
            phutil_tag('tt', array(), $daemon->getRunningAsUser()),
            phutil_tag('tt', array(), $phd_user));

          $this->newIssue('daemons.run-as-different-user')
            ->setName(pht('Daemons are running as the wrong user'))
            ->setSummary($summary)
            ->setMessage($message)
            ->addCommand('phabricator/ $ ./bin/phd restart');
        }
      }
    }
  }

}
