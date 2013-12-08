<?php

final class UploadArtifactBuildStepImplementation
  extends VariableBuildStepImplementation {

  public function getName() {
    return pht('Upload Artifact');
  }

  public function getGenericDescription() {
    return pht('Upload an artifact from a Drydock host to Phabricator.');
  }

  public function getDescription() {
    $settings = $this->getSettings();

    return pht(
      'Upload artifact located at \'%s\' on \'%s\'.',
      $settings['path'],
      $settings['hostartifact']);
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
      'filePHID' => $file->getPHID()));
    $artifact->save();
  }

  public function getArtifactMappings() {
    $settings = $this->getSettings();

    return array(
      $settings['name'] => HarbormasterBuildArtifact::TYPE_FILE);
  }

  public function validateSettings() {
    $settings = $this->getSettings();

    if ($settings['path'] === null || !is_string($settings['path'])) {
      return false;
    }
    if ($settings['name'] === null || !is_string($settings['name'])) {
      return false;
    }
    if ($settings['hostartifact'] === null ||
      !is_string($settings['hostartifact'])) {
      return false;
    }

    // TODO: Check if the host artifact is provided by previous build steps.

    return true;
  }

  public function getSettingDefinitions() {
    return array(
      'path' => array(
        'name' => 'Path',
        'description' =>
          'The path of the file that should be retrieved.  Note that on '.
          'Windows machines running FreeSSHD, this path will be relative '.
          'to the SFTP root path (configured under the SFTP tab).  You can '.
          'not specify an absolute path for Windows machines.',
        'type' => BuildStepImplementation::SETTING_TYPE_STRING),
      'name' => array(
        'name' => 'Local Name',
        'description' =>
          'The name for the file when it is stored in Phabricator.',
        'type' => BuildStepImplementation::SETTING_TYPE_STRING),
      'hostartifact' => array(
        'name' => 'Host Artifact',
        'description' =>
          'The host artifact that determines what machine the command '.
          'will run on.',
        'type' => BuildStepImplementation::SETTING_TYPE_ARTIFACT,
        'artifact_type' => HarbormasterBuildArtifact::TYPE_HOST));
  }

}
