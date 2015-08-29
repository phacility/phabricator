<?php

final class HarbormasterLeaseHostBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Lease Host');
  }

  public function getGenericDescription() {
    return pht('Obtain a lease on a Drydock host for performing builds.');
  }

  public function getBuildStepGroupKey() {
    return HarbormasterPrototypeBuildStepGroup::GROUPKEY;
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {

    $settings = $this->getSettings();

    // Create the lease.
    $lease = id(new DrydockLease())
      ->setResourceType('host')
      ->setOwnerPHID($build_target->getPHID())
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
    $artifact = $build_target->createArtifact(
      PhabricatorUser::getOmnipotentUser(),
      $settings['name'],
      HarbormasterHostArtifact::ARTIFACTCONST,
      array(
        'drydockLeasePHID' => $lease->getPHID(),
      ));
  }

  public function getArtifactOutputs() {
    return array(
      array(
        'name' => pht('Leased Host'),
        'key' => $this->getSetting('name'),
        'type' => HarbormasterHostArtifact::ARTIFACTCONST,
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
