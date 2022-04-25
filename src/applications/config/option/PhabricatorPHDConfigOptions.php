<?php

final class PhabricatorPHDConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Daemons');
  }

  public function getDescription() {
    return pht('Options relating to PHD (daemons).');
  }

  public function getIcon() {
    return 'fa-pied-piper-alt';
  }

  public function getGroup() {
    return 'core';
  }

  public function getOptions() {
    return array(
      $this->newOption('phd.log-directory', 'string', '/var/tmp/phd/log')
        ->setLocked(true)
        ->setDescription(
          pht('Directory that the daemons should use to store log files.')),
      $this->newOption('phd.taskmasters', 'int', 4)
        ->setLocked(true)
        ->setSummary(pht('Maximum taskmaster daemon pool size.'))
        ->setDescription(
          pht(
            "Maximum number of taskmaster daemons to run at once. Raising ".
            "this can increase the maximum throughput of the task queue. The ".
            "pool will automatically scale down when unutilized.".
            "\n\n".
            "If you are running a cluster, this limit applies separately ".
            "to each instance of `phd`. For example, if this limit is set ".
            "to `4` and you have three hosts running daemons, the effective ".
            "global limit will be 12.".
            "\n\n".
            "After changing this value, you must restart the daemons. Most ".
            "configuration changes are picked up by the daemons ".
            "automatically, but pool sizes can not be changed without a ".
            "restart.")),
      $this->newOption('phd.user', 'string', null)
        ->setLocked(true)
        ->setSummary(pht('System user to run daemons as.'))
        ->setDescription(
          pht(
            'Specify a system user to run the daemons as. Primarily, this '.
            'user will own the working copies of any repositories that '.
            'this software imports or manages. This option is new and '.
            'experimental.')),
      $this->newOption('phd.garbage-collection', 'wild', array())
        ->setLocked(true)
        ->setLockedMessage(
          pht(
            'This option can not be edited from the web UI. Use %s to adjust '.
            'garbage collector policies.',
            phutil_tag('tt', array(), 'bin/garbage set-policy')))
        ->setSummary(pht('Retention policies for garbage collection.'))
        ->setDescription(
          pht(
            'Customizes retention policies for garbage collectors.')),
    );
  }

}
