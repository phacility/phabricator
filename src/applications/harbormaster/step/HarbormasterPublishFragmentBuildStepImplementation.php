<?php

final class HarbormasterPublishFragmentBuildStepImplementation
  extends HarbormasterBuildStepImplementation {

  public function getName() {
    return pht('Publish Fragment');
  }

  public function getGenericDescription() {
    return pht('Publish a fragment based on a file artifact.');
  }


  public function getBuildStepGroupKey() {
    return HarbormasterPrototypeBuildStepGroup::GROUPKEY;
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
    $viewer = PhabricatorUser::getOmnipotentUser();

    $path = $this->mergeVariables(
      'vsprintf',
      $settings['path'],
      $variables);

    $artifact = $build_target->loadArtifact($settings['artifact']);
    $impl = $artifact->getArtifactImplementation();
    $file = $impl->loadArtifactFile($viewer);

    $fragment = id(new PhragmentFragmentQuery())
      ->setViewer($viewer)
      ->withPaths(array($path))
      ->executeOne();

    if ($fragment === null) {
      PhragmentFragment::createFromFile(
        $viewer,
        $file,
        $path,
        PhabricatorPolicies::getMostOpenPolicy(),
        PhabricatorPolicies::POLICY_USER);
    } else {
      if ($file->getMimeType() === 'application/zip') {
        $fragment->updateFromZIP($viewer, $file);
      } else {
        $fragment->updateFromFile($viewer, $file);
      }
    }
  }

  public function getArtifactInputs() {
    return array(
      array(
        'name' => pht('Publishes File'),
        'key' => $this->getSetting('artifact'),
        'type' => HarbormasterFileArtifact::ARTIFACTCONST,
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
