<?php

final class RemoteCommandBuildStepImplementation
  extends VariableBuildStepImplementation {

  public function getName() {
    return pht('Run Remote Command');
  }

  public function getGenericDescription() {
    return pht('Run a command on another machine.');
  }

  public function getDescription() {
    $settings = $this->getSettings();

    return pht(
      'Run \'%s\' on \'%s\'.',
      $settings['command'],
      $settings['sshhost']);
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildStep $build_step) {

    $settings = $this->getSettings();
    $variables = $this->retrieveVariablesFromBuild($build);

    $command = $this->mergeVariables(
      'vcsprintf',
      $settings['command'],
      $variables);

    $future = null;
    if (empty($settings['sshkey'])) {
      $future = new ExecFuture(
        'ssh -o "StrictHostKeyChecking no" -p %s %s %s',
        $settings['sshport'],
        $settings['sshuser'].'@'.$settings['sshhost'],
        $command);
    } else {
      $future = new ExecFuture(
        'ssh -o "StrictHostKeyChecking no" -p %s -i %s %s %s',
        $settings['sshport'],
        $settings['sshkey'],
        $settings['sshuser'].'@'.$settings['sshhost'],
        $command);
    }

    $log_stdout = $build->createLog($build_step, "remote", "stdout");
    $log_stderr = $build->createLog($build_step, "remote", "stderr");

    $start_stdout = $log_stdout->start();
    $start_stderr = $log_stderr->start();

    // Read the next amount of available output every second.
    while (!$future->isReady()) {
      list($stdout, $stderr) = $future->read();
      $log_stdout->append($stdout);
      $log_stderr->append($stderr);
      $future->discardBuffers();

      // Check to see if we have moved from a "Building" status.  This
      // can occur if the user cancels the build, in which case we want
      // to terminate whatever we're doing and return as quickly as possible.
      if ($build->checkForCancellation()) {
        $log_stdout->finalize($start_stdout);
        $log_stderr->finalize($start_stderr);
        $future->resolveKill();
        return;
      }

      // Wait one second before querying for more data.
      sleep(1);
    }

    // Get the return value so we can log that as well.
    list($err) = $future->resolve();

    // Retrieve the last few bits of information.
    list($stdout, $stderr) = $future->read();
    $log_stdout->append($stdout);
    $log_stderr->append($stderr);
    $future->discardBuffers();

    $log_stdout->finalize($start_stdout);
    $log_stderr->finalize($start_stderr);

    if ($err) {
      $build->setBuildStatus(HarbormasterBuild::STATUS_FAILED);
    }
  }

  public function validateSettings() {
    $settings = $this->getSettings();

    if ($settings['command'] === null || !is_string($settings['command'])) {
      return false;
    }
    if ($settings['sshhost'] === null || !is_string($settings['sshhost'])) {
      return false;
    }
    if ($settings['sshuser'] === null || !is_string($settings['sshuser'])) {
      return false;
    }
    if ($settings['sshkey'] === null || !is_string($settings['sshkey'])) {
      return false;
    }
    if ($settings['sshport'] === null || !is_int($settings['sshport']) ||
        $settings['sshport'] <= 0 || $settings['sshport'] >= 65536) {
      return false;
    }

    $whitelist = PhabricatorEnv::getEnvConfig(
      'harbormaster.temporary.hosts.whitelist');
    if (!in_array($settings['sshhost'], $whitelist)) {
      return false;
    }

    return true;
  }

  public function getSettingDefinitions() {
    return array(
      'command' => array(
        'name' => 'Command',
        'description' => 'The command to execute on the remote machine.',
        'type' => BuildStepImplementation::SETTING_TYPE_STRING),
      'sshhost' => array(
        'name' => 'SSH Host',
        'description' => 'The SSH host that the command will be run on.',
        'type' => BuildStepImplementation::SETTING_TYPE_STRING),
      'sshport' => array(
        'name' => 'SSH Port',
        'description' => 'The SSH port to connect to.',
        'type' => BuildStepImplementation::SETTING_TYPE_INTEGER,
        'default' => 22), // TODO: 'default' doesn't do anything yet..
      'sshuser' => array(
        'name' => 'SSH Username',
        'description' => 'The SSH username to use.',
        'type' => BuildStepImplementation::SETTING_TYPE_STRING),
      'sshkey' => array(
        'name' => 'SSH Identity File',
        'description' =>
          'The path to the SSH identity file (private key) '.
          'on the local web server.',
        'type' => BuildStepImplementation::SETTING_TYPE_STRING));
  }

}
