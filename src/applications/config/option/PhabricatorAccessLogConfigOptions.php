<?php

final class PhabricatorAccessLogConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Access Log");
  }

  public function getDescription() {
    return pht("Configure the access log, which logs all requests.");
  }

  public function getOptions() {
    $map = array(
      'c' => pht("The HTTP response code."),
      'C' => pht("The controller which handled the request."),
      'D' => pht("The request date."),
      'e' => pht("Epoch timestamp."),
      'h' => pht("The webserver's host name."),
      'p' => pht("The PID of the server process."),
      'R' => pht("The HTTP referrer."),
      'r' => pht("The remote IP."),
      'T' => pht("The request duration, in microseconds."),
      'U' => pht("The request path."),
      'u' => pht("The logged-in user, if one is logged in."),
      'M' => pht("The HTTP method."),
      'm' => pht("For conduit, the Conduit method which was invoked."),
    );

    $fdesc = pht("Format for the access log. Available variables are:");
    $fdesc .= "\n\n";
    foreach ($map as $key => $desc) {
      $fdesc .= "  - %".$key." ".$desc."\n";
    }
    $fdesc .= "\n";
    $fdesc .= pht(
      "If a variable isn't available (for example, %%m appears in the file ".
      "format but the request is not a Conduit request), it will be rendered ".
      "as '-'");
    $fdesc .= "\n\n";
    $fdesc .= pht(
      "Note that the default format is subject to change in the future, so ".
      "if you rely on the log's format, specify it explicitly.");

    return array(
      $this->newOption('log.access.path', 'string', null)
        ->setSummary(pht("Access log location."))
        ->setDescription(
          pht(
            "To enable the Phabricator access log, specify a path. The ".
            "access log can provide more detailed information about ".
            "Phabricator access than normal HTTP access logs (for instance, ".
            "it can show logged-in users, controllers, and other application ".
            "data).\n\n".
            "If not set, no log will be written."))
        ->addExample(
          null,
          pht('Disable access log'))
        ->addExample(
          '/var/log/phabricator/access.log',
          pht('Write access log here')),
      $this->newOption(
        'log.access.format',
        // NOTE: This is 'wild' intead of 'string' so "\t" and such can be
        // specified.
        'wild',
        "[%D]\t%p\t%h\t%r\t%u\t%C\t%m\t%U\t%R\t%c\t%T")
        ->setSummary(pht("Access log format."))
        ->setDescription($fdesc),
    );
  }

}
