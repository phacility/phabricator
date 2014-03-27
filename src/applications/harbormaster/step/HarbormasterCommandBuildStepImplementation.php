<?php

final class HarbormasterCommandBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Run Command');
  }

  public function getGenericDescription() {
    return pht('Run a command on Drydock host.');
  }

  public function getDescription() {
    return pht(
      'Run command %s on host %s.',
      $this->formatSettingForDescription('command'),
      $this->formatSettingForDescription('hostartifact'));
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {

    $settings = $this->getSettings();
    $variables = $build_target->getVariables();

    $command = $this->mergeVariables(
      'vcsprintf',
      $settings['command'],
      $variables);

    $artifact = $build->loadArtifact($settings['hostartifact']);

    $lease = $artifact->loadDrydockLease();

    $interface = $lease->getInterface('command');

    $future = $interface->getExecFuture('%C', $command);

    $log_stdout = $build->createLog($build_target, "remote", "stdout");
    $log_stderr = $build->createLog($build_target, "remote", "stderr");

    $start_stdout = $log_stdout->start();
    $start_stderr = $log_stderr->start();

    // Read the next amount of available output every second.
    while (!$future->isReady()) {
      list($stdout, $stderr) = $future->read();
      $log_stdout->append($stdout);
      $log_stderr->append($stderr);
      $future->discardBuffers();

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
      throw new Exception(pht('Command failed with error %d.', $err));
    }
  }

  public function getArtifactInputs() {
    return array(
      array(
        'name'  => pht('Run on Host'),
        'key'   => $this->getSetting('hostartifact'),
        'type'  => HarbormasterBuildArtifact::TYPE_HOST,
      ),
    );
  }

  public function getFieldSpecifications() {
    return array(
      'command' => array(
        'name' => pht('Command'),
        'type' => 'text',
        'required' => true,
      ),
      'hostartifact' => array(
        'name' => pht('Host'),
        'type' => 'text',
        'required' => true,
      ),
    );
  }

}
