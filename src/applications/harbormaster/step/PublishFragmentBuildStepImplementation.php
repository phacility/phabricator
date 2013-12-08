<?php

final class PublishFragmentBuildStepImplementation
  extends VariableBuildStepImplementation {

  public function getName() {
    return pht('Publish Fragment');
  }

  public function getGenericDescription() {
    return pht('Publish a fragment based on a file artifact.');
  }

  public function getDescription() {
    $settings = $this->getSettings();

    return pht(
      'Publish file artifact \'%s\' to the fragment path \'%s\'.',
      $settings['artifact'],
      $settings['path']);
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

    $artifact = $build->loadArtifact($settings['artifact']);

    $file = $artifact->loadPhabricatorFile();

    $fragment = id(new PhragmentFragmentQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPaths(array($path))
      ->executeOne();

    if ($fragment === null) {
      PhragmentFragment::createFromFile(
        PhabricatorUser::getOmnipotentUser(),
        $file,
        $path,
        PhabricatorPolicies::getMostOpenPolicy(),
        PhabricatorPolicies::POLICY_USER);
    } else {
      if ($file->getMimeType() === "application/zip") {
        $fragment->updateFromZIP(PhabricatorUser::getOmnipotentUser(), $file);
      } else {
        $fragment->updateFromFile(PhabricatorUser::getOmnipotentUser(), $file);
      }
    }
  }

  public function validateSettings() {
    $settings = $this->getSettings();

    if ($settings['path'] === null || !is_string($settings['path'])) {
      return false;
    }
    if ($settings['artifact'] === null ||
      !is_string($settings['artifact'])) {
      return false;
    }

    // TODO: Check if the file artifact is provided by previous build steps.

    return true;
  }

  public function getSettingDefinitions() {
    return array(
      'path' => array(
        'name' => 'Path',
        'description' =>
          'The path of the fragment that will be published.',
        'type' => BuildStepImplementation::SETTING_TYPE_STRING),
      'artifact' => array(
        'name' => 'File Artifact',
        'description' =>
          'The file artifact that will be published to Phragment.',
        'type' => BuildStepImplementation::SETTING_TYPE_ARTIFACT,
        'artifact_type' => HarbormasterBuildArtifact::TYPE_FILE));
  }

}
