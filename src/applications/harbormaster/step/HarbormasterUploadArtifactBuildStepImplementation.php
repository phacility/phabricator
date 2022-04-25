<?php

final class HarbormasterUploadArtifactBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Upload File');
  }

  public function getGenericDescription() {
    return pht('Upload a file.');
  }

  public function getBuildStepGroupKey() {
    return HarbormasterPrototypeBuildStepGroup::GROUPKEY;
  }

  public function getDescription() {
    return pht(
      'Upload %s from %s.',
      $this->formatSettingForDescription('path'),
      $this->formatSettingForDescription('hostartifact'));
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $settings = $this->getSettings();
    $variables = $build_target->getVariables();

    $path = $this->mergeVariables(
      'vsprintf',
      $settings['path'],
      $variables);

    $artifact = $build_target->loadArtifact($settings['hostartifact']);
    $impl = $artifact->getArtifactImplementation();
    $lease = $impl->loadArtifactLease($viewer);

    $interface = $lease->getInterface('filesystem');

    // TODO: Handle exceptions.
    $file = $interface->saveFile($path, $settings['name']);

    // Insert the artifact record.
    $artifact = $build_target->createArtifact(
      $viewer,
      $settings['name'],
      HarbormasterFileArtifact::ARTIFACTCONST,
      array(
        'filePHID' => $file->getPHID(),
      ));
  }

  public function getArtifactInputs() {
    return array(
      array(
        'name' => pht('Upload From Host'),
        'key' => $this->getSetting('hostartifact'),
        'type' => HarbormasterHostArtifact::ARTIFACTCONST,
      ),
    );
  }

  public function getArtifactOutputs() {
    return array(
      array(
        'name' => pht('Uploaded File'),
        'key' => $this->getSetting('name'),
        'type' => HarbormasterHostArtifact::ARTIFACTCONST,
      ),
    );
  }

  public function getFieldSpecifications() {
    return array(
      'path' => array(
        'name' => pht('Path'),
        'type' => 'text',
        'required' => true,
      ),
      'name' => array(
        'name' => pht('Local Name'),
        'type' => 'text',
        'required' => true,
      ),
      'hostartifact' => array(
        'name' => pht('Host Artifact'),
        'type' => 'text',
        'required' => true,
      ),
    );
  }

}
