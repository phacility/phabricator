<?php

final class PhabricatorPHDConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("Daemons");
  }

  public function getDescription() {
    return pht("Options relating to PHD (daemons).");
  }

  public function getOptions() {
    return array(
      $this->newOption('phd.pid-directory', 'string', '/var/tmp/phd/pid')
        ->setDescription(
          pht(
            "Directory that phd should use to track running daemons.")),
      $this->newOption('phd.log-directory', 'string', '/var/tmp/phd/log')
        ->setDescription(
          pht(
            "Directory that the daemons should use to store log files.")),
      $this->newOption('phd.start-taskmasters', 'int', 4)
        ->setSummary(pht("Number of TaskMaster daemons to start by default."))
        ->setDescription(
          pht(
            "Number of 'TaskMaster' daemons that 'phd start' should start. ".
            "You can raise this if you have a task backlog, or explicitly ".
            "launch more with 'phd launch <N> taskmaster'.")),
      $this->newOption('phd.verbose', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Verbose mode"),
            pht("Normal mode"),
          ))
        ->setSummary(pht("Launch daemons in 'verbose' mode by default."))
        ->setDescription(
          pht(
            "Launch daemons in 'verbose' mode by default. This creates a lot ".
            "of output, but can help debug issues. Daemons launched in debug ".
            "mode with 'phd debug' are always launched in verbose mode. See ".
            "also 'phd.trace'.")),
      $this->newOption('phd.trace', 'bool', false)
        ->setBoolOptions(
          array(
            pht("Trace mode"),
            pht("Normal mode"),
          ))
        ->setSummary(pht("Launch daemons in 'trace' mode by default."))
        ->setDescription(
          pht(
            "Launch daemons in 'trace' mode by default. This creates an ".
            "ENORMOUS amount of output, but can help debug issues. Daemons ".
            "launched in debug mode with 'phd debug' are always launched in ".
            "trace mdoe. See also 'phd.verbose'.")),
    );
  }

}
