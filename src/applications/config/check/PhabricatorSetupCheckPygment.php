<?php

final class PhabricatorSetupCheckPygment extends PhabricatorSetupCheck {

  protected function executeChecks() {

    $pygment = PhabricatorEnv::getEnvConfig('pygments.enabled');

    if ($pygment) {
      list($err) = exec_manual('pygmentize -h');
      if ($err) {
        $summary = pht(
          'You enabled pygments but the pygmentize script is not '.
          'actually available, your $PATH is probably broken.');

        $message = pht(
          'The environmental variable $PATH does not contain '.
          'pygmentize. You have enabled pygments, which requires '.
          'pygmentize to be available in your $PATH variable.');

        $this
          ->newIssue('pygments.enabled')
          ->setName(pht('pygmentize Not Found'))
          ->setSummary($summary)
          ->setMessage($message)
          ->addPhabricatorConfig('pygments.enabled')
          ->addPhabricatorConfig('envinronment.append-paths');
      }
    }
  }
}
