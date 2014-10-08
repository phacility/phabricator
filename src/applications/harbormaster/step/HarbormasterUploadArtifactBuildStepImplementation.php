<?php

final class HarbormasterUploadArtifactBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Upload File');
  }

  public function getGenericDescription() {
    return pht('Upload a file from a host to Phabricator.');
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

    $settings = $this->getSettings();
    $variables = $build_target->getVariables();

    $path = $this->mergeVariables(
      'vsprintf',
      $settings['path'],
      $variables);

    $artifact = $build->loadArtifact($settings['hostartifact']);

    $lease = $artifact->loadDrydockLease();

    $interface = $lease->getInterface('filesystem');

    // TODO: Handle exceptions.
    $file = $interface->saveFile($path, $settings['name']);

    // Insert the artifact record.
    $artifact = $build->createArtifact(
      $build_target,
      $settings['name'],
      HarbormasterBuildArtifact::TYPE_FILE);
    $artifact->setArtifactData(array(
      'filePHID' => $file->getPHID(),
    ));
    $artifact->save();
  }

  public function getArtifactInputs() {
    return array(
      array(
        'name' => pht('Upload From Host'),
        'key' => $this->getSetting('hostartifact'),
        'type' => HarbormasterBuildArtifact::TYPE_HOST,
      ),
    );
  }

  public function getArtifactOutputs() {
    return array(
      array(
        'name' => pht('Uploaded File'),
        'key' => $this->getSetting('name'),
        'type' => HarbormasterBuildArtifact::TYPE_FILE,
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
