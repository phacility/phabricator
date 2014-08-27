<?php

final class PhabricatorSetupCheckDaemons extends PhabricatorSetupCheck {

  protected function executeChecks() {

    $task_daemon = id(new PhabricatorDaemonLogQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withStatus(PhabricatorDaemonLogQuery::STATUS_ALIVE)
      ->withDaemonClasses(array('PhabricatorTaskmasterDaemon'))
      ->setLimit(1)
      ->execute();

    if (!$task_daemon) {
      $doc_href = PhabricatorEnv::getDocLink(
        'Managing Daemons with phd');

      $summary = pht(
        'You must start the Phabricator daemons to send email, rebuild '.
        'search indexes, and do other background processing.');

      $message = pht(
        'The Phabricator daemons are not running, so Phabricator will not '.
        'be able to perform background processing (including sending email, '.
        'rebuilding search indexes, importing commits, cleaning up old data, '.
        'running builds, etc.).'.
        "\n\n".
        'Use %s to start daemons. See %s for more information.',
        phutil_tag('tt', array(), 'bin/phd start'),
        phutil_tag(
          'a',
          array(
            'href' => $doc_href,
            'target' => '_blank'
          ),
          pht('Managing Daemons with phd')));

      $this->newIssue('daemons.not-running')
        ->setShortName(pht('Daemons Not Running'))
        ->setName(pht('Phabricator Daemons Are Not Running'))
        ->setSummary($summary)
        ->setMessage($message)
        ->addCommand('phabricator/ $ ./bin/phd start');
    }

    $environment_hash = PhabricatorEnv::calculateEnvironmentHash();
    $all_daemons = id(new PhabricatorDaemonLogQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withStatus(PhabricatorDaemonLogQuery::STATUS_ALIVE)
      ->execute();
    foreach ($all_daemons as $daemon) {
      if ($daemon->getEnvHash() != $environment_hash) {
        $doc_href = PhabricatorEnv::getDocLink(
          'Managing Daemons with phd');

        $summary = pht(
          'You should restart the daemons. Their configuration is out of '.
          'date.');

        $message = pht(
          'The Phabricator daemons are running with an out of date '.
          'configuration. If you are making multiple configuration changes, '.
          'you only need to restart the daemons once after the last change.'.
          "\n\n".
          'Use %s to restart daemons. See %s for more information.',
          phutil_tag('tt', array(), 'bin/phd restart'),
          phutil_tag(
            'a',
            array(
              'href' => $doc_href,
              'target' => '_blank'
            ),
            pht('Managing Daemons with phd')));

        $this->newIssue('daemons.need-restarting')
          ->setShortName(pht('Daemons Need Restarting'))
          ->setName(pht('Phabricator Daemons Need Restarting'))
          ->setSummary($summary)
          ->setMessage($message)
          ->addCommand('phabricator/ $ ./bin/phd restart');
        break;
      }
    }
  }

}
