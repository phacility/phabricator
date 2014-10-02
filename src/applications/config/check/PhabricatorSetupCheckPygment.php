<?php

final class PhabricatorSetupCheckPygment extends PhabricatorSetupCheck {

  protected function executeChecks() {

    $pygment = PhabricatorEnv::getEnvConfig('pygments.enabled');

    if ($pygment) {
      if (!Filesystem::binaryExists('pygmentize')) {
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
          ->addRelatedPhabricatorConfig('pygments.enabled')
          ->addPhabricatorConfig('environment.append-paths');
      } else {
        list($err) = exec_manual('pygmentize -h');
        if ($err) {
          $summary = pht(
            'You have enabled pygments and the pygmentize script is '.
            'available, but does not seem to work.');

          $message = pht(
            'Phabricator has %s available in $PATH, but the binary '.
            'exited with an error code when run as %s. Check that it is '.
            'installed correctly.',
            phutil_tag('tt', array(), 'pygmentize'),
            phutil_tag('tt', array(), 'pygmentize -h'));

          $this
            ->newIssue('pygments.failed')
            ->setName(pht('pygmentize Not Working'))
            ->setSummary($summary)
            ->setMessage($message)
            ->addRelatedPhabricatorConfig('pygments.enabled')
            ->addPhabricatorConfig('environment.append-paths');
        }
      }
    }
  }
}
