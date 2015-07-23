<?php

final class HarbormasterCommandBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  private $platform;

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

  public function escapeCommand($pattern, array $args) {
    array_unshift($args, $pattern);

    $mode = PhutilCommandString::MODE_DEFAULT;
    if ($this->platform == 'windows') {
      $mode = PhutilCommandString::MODE_POWERSHELL;
    }

    return id(new PhutilCommandString($args))
      ->setEscapingMode($mode);
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {

    $settings = $this->getSettings();
    $variables = $build_target->getVariables();

    $artifact = $build->loadArtifact($settings['hostartifact']);

    $lease = $artifact->loadDrydockLease();

    $this->platform = $lease->getAttribute('platform');

    $command = $this->mergeVariables(
      array($this, 'escapeCommand'),
      $settings['command'],
      $variables);

    $this->platform = null;

    $interface = $lease->getInterface('command');

    $future = $interface->getExecFuture('%C', $command);

    $log_stdout = $build->createLog($build_target, 'remote', 'stdout');
    $log_stderr = $build->createLog($build_target, 'remote', 'stderr');

    $start_stdout = $log_stdout->start();
    $start_stderr = $log_stderr->start();

    $build_update = 5;

    // Read the next amount of available output every second.
    $futures = new FutureIterator(array($future));
    foreach ($futures->setUpdateInterval(1) as $key => $future_iter) {
      if ($future_iter === null) {

        // Check to see if we should abort.
        if ($build_update <= 0) {
          $build->reload();
          if ($this->shouldAbort($build, $build_target)) {
            $future->resolveKill();
            throw new HarbormasterBuildAbortedException();
          } else {
            $build_update = 5;
          }
        } else {
          $build_update -= 1;
        }

        // Command is still executing.

        // Read more data as it is available.
        list($stdout, $stderr) = $future->read();
        $log_stdout->append($stdout);
        $log_stderr->append($stderr);
        $future->discardBuffers();
      } else {
        // Command execution is complete.

        // Get the return value so we can log that as well.
        list($err) = $future->resolve();

        // Retrieve the last few bits of information.
        list($stdout, $stderr) = $future->read();
        $log_stdout->append($stdout);
        $log_stderr->append($stderr);
        $future->discardBuffers();

        break;
      }
    }

    $log_stdout->finalize($start_stdout);
    $log_stderr->finalize($start_stderr);

    if ($err) {
      throw new HarbormasterBuildFailureException();
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
        'caption' => pht(
          "Under Windows, this is executed under PowerShell. ".
          "Under UNIX, this is executed using the user's shell."),
      ),
      'hostartifact' => array(
        'name' => pht('Host'),
        'type' => 'text',
        'required' => true,
      ),
    );
  }

}
