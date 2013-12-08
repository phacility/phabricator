<?php

final class LeaseHostBuildStepImplementation
  extends BuildStepImplementation {

  public function getName() {
    return pht('Lease Host');
  }

  public function getGenericDescription() {
    return pht('Obtain a lease on a Drydock host for performing builds.');
  }

  public function getDescription() {
    $settings = $this->getSettings();

    return pht(
      'Obtain a lease on a Drydock host whose platform is \'%s\' and store '.
      'the resulting lease in a host artifact called \'%s\'.',
      $settings['platform'],
      $settings['name']);
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {

    $settings = $this->getSettings();

    // Create the lease.
    $lease = new DrydockLease();
    $lease->setResourceType('host');
    $lease->setAttributes(
      array('platform' => $settings['platform']));
    $lease->queueForActivation();

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

  public function getArtifactMappings() {
    $settings = $this->getSettings();

    return array(
      $settings['name'] => HarbormasterBuildArtifact::TYPE_HOST);
  }

  public function validateSettings() {
    $settings = $this->getSettings();

    if ($settings['name'] === null || !is_string($settings['name'])) {
      return false;
    }
    if ($settings['platform'] === null || !is_string($settings['platform'])) {
      return false;
    }

    return true;
  }

  public function getSettingDefinitions() {
    return array(
      'name' => array(
        'name' => 'Artifact Name',
        'description' =>
          'The name of the artifact to reference in future build steps.',
        'type' => BuildStepImplementation::SETTING_TYPE_STRING),
      'platform' => array(
        'name' => 'Platform',
        'description' => 'The platform of the host.',
        'type' => BuildStepImplementation::SETTING_TYPE_STRING));
  }

}
