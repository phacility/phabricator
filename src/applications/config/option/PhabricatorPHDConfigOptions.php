<?php

final class PhabricatorPHDConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Daemons');
  }

  public function getDescription() {
    return pht('Options relating to PHD (daemons).');
  }

  public function getFontIcon() {
    return 'fa-pied-piper-alt';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    return array(
      $this->newOption('phd.pid-directory', 'string', '/var/tmp/phd/pid')
        ->setDescription(
          pht('Directory that phd should use to track running daemons.')),
      $this->newOption('phd.log-directory', 'string', '/var/tmp/phd/log')
        ->setDescription(
          pht('Directory that the daemons should use to store log files.')),
      $this->newOption('phd.taskmasters', 'int', 4)
        ->setSummary(pht('Maximum taskmaster daemon pool size.'))
        ->setDescription(
          pht(
            'Maximum number of taskmaster daemons to run at once. Raising '.
            'this can increase the maximum throughput of the task queue. The '.
            'pool will automatically scale down when unutilized.')),
      $this->newOption('phd.verbose', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Verbose mode'),
            pht('Normal mode'),
          ))
        ->setSummary(pht("Launch daemons in 'verbose' mode by default."))
        ->setDescription(
          pht(
            "Launch daemons in 'verbose' mode by default. This creates a lot ".
            "of output, but can help debug issues. Daemons launched in debug ".
            "mode with '%s' are always launched in verbose mode. ".
            "See also '%s'.",
            'phd debug',
            'phd.trace')),
      $this->newOption('phd.user', 'string', null)
        ->setLocked(true)
        ->setSummary(pht('System user to run daemons as.'))
        ->setDescription(
          pht(
            'Specify a system user to run the daemons as. Primarily, this '.
            'user will own the working copies of any repositories that '.
            'Phabricator imports or manages. This option is new and '.
            'experimental.')),
      $this->newOption('phd.trace', 'bool', false)
        ->setBoolOptions(
          array(
            pht('Trace mode'),
            pht('Normal mode'),
          ))
        ->setSummary(pht("Launch daemons in 'trace' mode by default."))
        ->setDescription(
          pht(
            "Launch daemons in 'trace' mode by default. This creates an ".
            "ENORMOUS amount of output, but can help debug issues. Daemons ".
            "launched in debug mode with '%s' are always launched in ".
            "trace mode. See also '%s'.",
            'phd debug',
            'phd.verbose')),
      $this->newOption('phd.variant-config', 'list<string>', array())
        ->setDescription(
          pht(
            'Specify config keys that can safely vary between the web tier '.
            'and the daemons. Primarily, this is a way to suppress the '.
            '"Daemons and Web Have Different Config" setup issue on a per '.
            'config key basis.')),
    );
  }

}
