<?php

final class PhabricatorSetupCheckBaseURI extends PhabricatorSetupCheck {

  protected function executeChecks() {
    $base_uri = PhabricatorEnv::getEnvConfig('phabricator.base-uri');
    if ($base_uri) {
      return;
    }

    $base_uri_guess = PhabricatorEnv::getRequestBaseURI();

    $summary = pht(
      'The base URI for this install is not configured. Configuring it will '.
      'improve security and enable features.');

    $message = pht(
      'The base URI for this install is not configured. Configuring it will '.
      'improve security and allow background processes (like daemons and '.
      'scripts) to generate links.'.
      "\n\n".
      'You should set the base URI to the URI you will use to access '.
      'Phabricator, like "http://phabricator.example.com/".'.
      "\n\n".
      'Include the protocol (http or https), domain name, and port number if '.
      'you are using a port other than 80 (http) or 443 (https).'.
      "\n\n".
      'Based on this request, it appears that the correct setting is:'.
      "\n\n".
      '%s'.
      "\n\n".
      'To configure the base URI, run the command shown below.',
      $base_uri_guess);

    $this
      ->newIssue('config.phabricator.base-uri')
      ->setShortName(pht('No Base URI'))
      ->setName(pht("Base URI Not Configured"))
      ->setSummary($summary)
      ->setMessage($message)
      ->addCommand(
        csprintf(
          '<tt>phabricator/ $</tt> '.
          './bin/config set phabricator.base-uri %s',
          $base_uri_guess));
  }
}
