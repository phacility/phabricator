<?php

final class PhabricatorBaseURISetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_IMPORTANT;
  }

  protected function executeChecks() {
    $base_uri = PhabricatorEnv::getEnvConfig('phabricator.base-uri');

    $host_header = AphrontRequest::getHTTPHeader('Host');
    if (strpos($host_header, '.') === false) {
      if ($host_header === null || !strlen(trim($host_header))) {
        $name = pht('No "Host" Header');
        $summary = pht('No "Host" header present in request.');
        $message = pht(
          'This request did not include a "Host" header. This may mean that '.
          'your webserver (like nginx or apache) is misconfigured so the '.
          '"Host" header is not making it to this software, or that you are '.
          'making a raw request without a "Host" header using a tool or '.
          'library.'.
          "\n\n".
          'If you are using a web browser, check your webserver '.
          'configuration. If you are using a tool or library, check how the '.
          'request is being constructed.'.
          "\n\n".
          'It is also possible (but very unlikely) that some other network '.
          'device (like a load balancer) is stripping the header.'.
          "\n\n".
          'Requests must include a valid "Host" header.');
      } else {
        $name = pht('Bad "Host" Header');
        $summary = pht('Request has bad "Host" header.');
        $message = pht(
          'This request included an invalid "Host" header, with value "%s". '.
          'Host headers must contain a dot ("."), like "example.com". This '.
          'is required for some browsers to be able to set cookies.'.
          "\n\n".
          'This may mean the base URI is configured incorrectly. You must '.
          'serve this software from a base URI with a dot (like '.
          '"https://devtools.example.com"), not a bare domain '.
          '(like "https://devtools/"). If you are trying to use a bare '.
          'domain, change your configuration to use a full domain with a dot '.
          'in it instead.'.
          "\n\n".
          'This might also mean that your webserver (or some other network '.
          'device, like a load balancer) is mangling the "Host" header, or '.
          'you are using a tool or library to issue a request manually and '.
          'setting the wrong "Host" header.'.
          "\n\n".
          'Requests must include a valid "Host" header.',
          $host_header);
      }

      $this
        ->newIssue('request.host')
        ->setName($name)
        ->setSummary($summary)
        ->setMessage($message)
        ->setIsFatal(true);
    }

    if ($base_uri) {
      return;
    }

    $base_uri_guess = PhabricatorEnv::getRequestBaseURI();

    $summary = pht(
      'The base URI for this install is not configured. Many major features '.
      'will not work properly until you configure it.');

    $message = pht(
      'The base URI for this install is not configured, and major features '.
      'will not work properly until you configure it.'.
      "\n\n".
      'You should set the base URI to the URI you will use to access '.
      'this server, like "http://devtools.example.com/".'.
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
      ->setName(pht('Base URI Not Configured'))
      ->setSummary($summary)
      ->setMessage($message)
      ->addCommand(
        hsprintf(
          '<tt>$</tt> %s',
          csprintf(
            './bin/config set phabricator.base-uri %s',
            $base_uri_guess)));
  }
}
