<?php

final class HarbormasterPublishFragmentBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Publish Fragment');
  }

  public function getGenericDescription() {
    return pht('Publish a fragment based on a file artifact.');
  }

  public function getDescription() {
    return pht(
      'Publish file artifact %s as fragment %s.',
      $this->formatSettingForDescription('artifact'),
      $this->formatSettingForDescription('path'));
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
      if ($file->getMimeType() === 'application/zip') {
        $fragment->updateFromZIP(PhabricatorUser::getOmnipotentUser(), $file);
      } else {
        $fragment->updateFromFile(PhabricatorUser::getOmnipotentUser(), $file);
      }
    }
  }

  public function getArtifactInputs() {
    return array(
      array(
        'name' => pht('Publishes File'),
        'key' => $this->getSetting('artifact'),
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
      'artifact' => array(
        'name' => pht('File Artifact'),
        'type' => 'text',
        'required' => true,
      ),
    );
  }

}
