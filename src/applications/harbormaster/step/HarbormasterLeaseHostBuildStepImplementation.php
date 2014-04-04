<?php

final class HarbormasterLeaseHostBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Lease Host');
  }

  public function getGenericDescription() {
    return pht('Obtain a lease on a Drydock host for performing builds.');
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {

    $settings = $this->getSettings();

    // Create the lease.
    $lease = id(new DrydockLease())
      ->setResourceType('host')
      ->setAttributes(
        array(
          'platform' => $settings['platform'],
        ))
      ->queueForActivation();

    // Wait until the lease is fulfilled.
    // TODO: This will throw an exception if the lease can't be fulfilled;
    // we should treat that as build failure not build error.
    $lease->waitUntilActive();

    // Create the associated artifact.
    $artifact = $build->createArtifact(
      $build_target,
      $settings['name'],
      HarbormasterBuildArtifact::TYPE_HOST);
    $artifact->setArtifactData(array(
      'drydock-lease' => $lease->getID()));
    $artifact->save();
  }

  public function getArtifactOutputs() {
    return array(
      array(
        'name' => pht('Leased Host'),
        'key' => $this->getSetting('name'),
        'type' => HarbormasterBuildArtifact::TYPE_HOST,
      ),
    );
  }

  public function getFieldSpecifications() {
    return array(
      'name' => array(
        'name' => pht('Artifact Name'),
        'type' => 'text',
        'required' => true,
      ),
      'platform' => array(
        'name' => pht('Platform'),
        'type' => 'text',
        'required' => true,
      ),
    );
  }

}
