<?php

final class PhabricatorSecuritySetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_OTHER;
  }

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
        'To learn more about how this issue affects this software, see %s.',
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

    $file_key = 'security.alternate-file-domain';
    $file_domain = PhabricatorEnv::getEnvConfig($file_key);
    if (!$file_domain) {
      $doc_href = PhabricatorEnv::getDoclink('Configuring a File Domain');

      $this->newIssue('security.'.$file_key)
        ->setName(pht('Alternate File Domain Not Configured'))
        ->setSummary(
          pht(
            'Improve security by configuring an alternate file domain.'))
        ->setMessage(
          pht(
            'This software is currently configured to serve user uploads '.
            'directly from the same domain as other content. This is a '.
            'security risk.'.
            "\n\n".
            'Configure a CDN (or alternate file domain) to eliminate this '.
            'risk. Using a CDN will also improve performance. See the '.
            'guide below for instructions.'))
        ->addPhabricatorConfig($file_key)
        ->addLink(
          $doc_href,
          pht('Configuration Guide: Configuring a File Domain'));
    }
  }
}
