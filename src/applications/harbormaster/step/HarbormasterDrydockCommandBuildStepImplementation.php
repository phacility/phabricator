<?php

final class HarbormasterDrydockCommandBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Drydock: Run Command');
  }

  public function getGenericDescription() {
    return pht('Run a command on Drydock resource.');
  }

  public function getBuildStepGroupKey() {
    return HarbormasterDrydockBuildStepGroup::GROUPKEY;
  }

  public function getDescription() {
    return pht(
      'Run command %s on %s.',
      $this->formatSettingForDescription('command'),
      $this->formatSettingForDescription('artifact'));
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $settings = $this->getSettings();
    $variables = $build_target->getVariables();

    $artifact = $build_target->loadArtifact($settings['artifact']);
    $impl = $artifact->getArtifactImplementation();
    $lease = $impl->loadArtifactLease($viewer);

    // TODO: Require active lease.

    $command = $this->mergeVariables(
      'vcsprintf',
      $settings['command'],
      $variables);

    $interface = $lease->getInterface(DrydockCommandInterface::INTERFACE_TYPE);

    $exec_future = $interface->getExecFuture('%C', $command);

    $harbor_future = id(new HarbormasterExecFuture())
      ->setFuture($exec_future)
      ->setLogs(
        $build_target->newLog('remote', 'stdout'),
        $build_target->newLog('remote', 'stderr'));

    $this->resolveFutures(
      $build,
      $build_target,
      array($harbor_future));

    list($err) = $harbor_future->resolve();
    if ($err) {
      throw new HarbormasterBuildFailureException();
    }
  }

  public function getArtifactInputs() {
    return array(
      array(
        'name' => pht('Drydock Lease'),
        'key' => $this->getSetting('artifact'),
        'type' => HarbormasterWorkingCopyArtifact::ARTIFACTCONST,
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
      'artifact' => array(
        'name' => pht('Drydock Lease'),
        'type' => 'text',
        'required' => true,
      ),
    );
  }

}
