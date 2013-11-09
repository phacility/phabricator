<?php

abstract class VariableBuildStepImplementation extends BuildStepImplementation {

  public function retrieveVariablesFromBuild(HarbormasterBuild $build) {
    $results = array(
      'buildable.revision' => null,
      'buildable.commit' => null,
      'repository.callsign' => null,
      'repository.vcs' => null,
      'repository.uri' => null,
      'step.timestamp' => null,
      'build.id' => null);

    $buildable = $build->getBuildable();
    $object = $buildable->getBuildableObject();

    $repo = null;
    if ($object instanceof DifferentialRevision) {
      $results['buildable.revision'] = $object->getID();
      $repo = $object->getRepository();
    } else if ($object instanceof PhabricatorRepositoryCommit) {
      $results['buildable.commit'] = $object->getCommitIdentifier();
      $repo = $object->getRepository();
    }

    $results['repository.callsign'] = $repo->getCallsign();
    $results['repository.vcs'] = $repo->getVersionControlSystem();
    $results['repository.uri'] = $repo->getPublicRemoteURI();
    $results['step.timestamp'] = time();
    $results['build.id'] = $build->getID();

    return $results;
  }

  public function mergeVariables(HarbormasterBuild $build, $string) {
    $variables = $this->retrieveVariablesFromBuild($build);
    foreach ($variables as $name => $value) {
      if ($value === null) {
        $value = '';
      }
      $string = str_replace('${'.$name.'}', $value, $string);
    }
    return $string;
  }

  public function getAvailableVariables() {
    return array(
      'buildable.revision' =>
        pht('The differential revision ID, if applicable.'),
      'buildable.commit' => pht('The commit identifier, if applicable.'),
      'repository.callsign' =>
        pht('The callsign of the repository in Phabricator.'),
      'repository.vcs' =>
        pht('The version control system, either "svn", "hg" or "git".'),
      'repository.uri' =>
        pht('The URI to clone or checkout the repository from.'),
      'step.timestamp' => pht('The current UNIX timestamp.'),
      'build.id' => pht('The ID of the current build.'));
  }

  public function getSettingRemarkupInstructions() {
    $text = '';
    $text .= pht('The following variables are available: ')."\n";
    $text .= "\n";
    foreach ($this->getAvailableVariables() as $name => $desc) {
      $text .= '  - `'.$name.'`: '.$desc."\n";
    }
    $text .= "\n";
    $text .= "Use `\${name}` to merge a variable into a setting.";
    return $text;
  }

}
