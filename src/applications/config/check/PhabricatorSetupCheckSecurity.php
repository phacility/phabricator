<?php

final class PhabricatorSetupCheckSecurity extends PhabricatorSetupCheck {

  protected function executeChecks() {

    // This checks for a version of bash with the "Shellshock" vulnerability.
    // For details, see T6185.

    $payload = array(
      'SHELLSHOCK_PAYLOAD' => '() { :;} ; echo VULNERABLE',
    );

    list($err, $stdout) = id(new ExecFuture('echo shellshock-test'))
      ->setEnv($payload, $wipe_process_env = true)
      ->resolve();

    if (!$err && preg_match('/VULNERABLE/', $stdout)) {
      $summary = pht(
        'This system has an unpatched version of Bash with a severe, widely '.
        'disclosed vulnerability.');

      $message = pht(
        'The version of %s on this system is out of date and contains a '.
        'major, widely disclosed vulnerability (the "Shellshock" '.
        'vulnerability).'.
        "\n\n".
        'Upgrade %s to a patched version.'.
        "\n\n".
        'To learn more about how this issue affects Phabricator, see %s.',
        phutil_tag('tt', array(), 'bash'),
        phutil_tag('tt', array(), 'bash'),
        phutil_tag(
          'a',
          array(
            'href' => 'https://secure.phabricator.com/T6185',
            'target' => '_blank',
          ),
          pht('T6185 "Shellshock" Bash Vulnerability')));

      $this
        ->newIssue('security.shellshock')
        ->setName(pht('Severe Security Vulnerability: Unpatched Bash'))
        ->setSummary($summary)
        ->setMessage($message);
    }

  }
}
