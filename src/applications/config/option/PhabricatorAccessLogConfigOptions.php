<?php

final class PhabricatorAccessLogConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Access Logs');
  }

  public function getDescription() {
    return pht('Configure the access logs, which log HTTP/SSH requests.');
  }

  public function getFontIcon() {
    return 'fa-list';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    $common_map = array(
      'C' => pht('The controller or workflow which handled the request.'),
      'c' => pht('The HTTP response code or process exit code.'),
      'D' => pht('The request date.'),
      'e' => pht('Epoch timestamp.'),
      'h' => pht("The webserver's host name."),
      'p' => pht('The PID of the server process.'),
      'r' => pht('The remote IP.'),
      'T' => pht('The request duration, in microseconds.'),
      'U' => pht('The request path, or request target.'),
      'm' => pht('For conduit, the Conduit method which was invoked.'),
      'u' => pht('The logged-in username, if one is logged in.'),
      'P' => pht('The logged-in user PHID, if one is logged in.'),
      'i' => pht('Request input, in bytes.'),
      'o' => pht('Request output, in bytes.'),
    );

    $http_map = $common_map + array(
      'R' => pht('The HTTP referrer.'),
      'M' => pht('The HTTP method.'),
    );

    $ssh_map = $common_map + array(
      's' => pht('The system user.'),
      'S' => pht('The system sudo user.'),
      'k' => pht('ID of the SSH key used to authenticate the request.'),
    );

    $http_desc = pht(
      'Format for the HTTP access log. Use `%s` to set the path. '.
      'Available variables are:',
      'log.access.path');
    $http_desc .= "\n\n";
    $http_desc .= $this->renderMapHelp($http_map);

    $ssh_desc = pht(
      'Format for the SSH access log. Use %s to set the path. '.
      'Available variables are:',
      'log.ssh.path');
    $ssh_desc .= "\n\n";
    $ssh_desc .= $this->renderMapHelp($ssh_map);

    return array(
      $this->newOption('log.access.path', 'string', null)
        ->setLocked(true)
        ->setSummary(pht('Access log location.'))
        ->setDescription(
          pht(
            "To enable the Phabricator access log, specify a path. The ".
            "Phabricator access than normal HTTP access logs (for instance, ".
            "it can show logged-in users, controllers, and other application ".
            "data).\n\n".
            "If not set, no log will be written."))
        ->addExample(
          null,
          pht('Disable access log.'))
        ->addExample(
          '/var/log/phabricator/access.log',
          pht('Write access log here.')),
      $this->newOption(
        'log.access.format',
        // NOTE: This is 'wild' intead of 'string' so "\t" and such can be
        // specified.
        'wild',
        "[%D]\t%p\t%h\t%r\t%u\t%C\t%m\t%U\t%R\t%c\t%T")
        ->setLocked(true)
        ->setSummary(pht('Access log format.'))
        ->setDescription($http_desc),
      $this->newOption('log.ssh.path', 'string', null)
        ->setLocked(true)
        ->setSummary(pht('SSH log location.'))
        ->setDescription(
          pht(
            "To enable the Phabricator SSH log, specify a path. The ".
            "access log can provide more detailed information about SSH ".
            "access than a normal SSH log (for instance, it can show ".
            "logged-in users, commands, and other application data).\n\n".
            "If not set, no log will be written."))
        ->addExample(
          null,
          pht('Disable SSH log.'))
        ->addExample(
          '/var/log/phabricator/ssh.log',
          pht('Write SSH log here.')),
      $this->newOption(
        'log.ssh.format',
        'wild',
        "[%D]\t%p\t%h\t%r\t%s\t%S\t%u\t%C\t%U\t%c\t%T\t%i\t%o")
        ->setLocked(true)
        ->setSummary(pht('SSH log format.'))
        ->setDescription($ssh_desc),
    );
  }

  private function renderMapHelp(array $map) {
    $desc = '';
    foreach ($map as $key => $kdesc) {
      $desc .= "  - `%".$key."` ".$kdesc."\n";
    }
    $desc .= "\n";
    $desc .= pht(
      "If a variable isn't available (for example, %%m appears in the file ".
      "format but the request is not a Conduit request), it will be rendered ".
      "as '-'");
    $desc .= "\n\n";
    $desc .= pht(
      "Note that the default format is subject to change in the future, so ".
      "if you rely on the log's format, specify it explicitly.");

    return $desc;
  }

}
