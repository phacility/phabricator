<?php

abstract class VariableBuildStepImplementation extends BuildStepImplementation {

  public function retrieveVariablesFromBuild(HarbormasterBuild $build) {
    $results = array(
      'revision' => null,
      'commit' => null,
      'repository' => null,
      'vcs' => null,
      'uri' => null,
      'timestamp' => null);

    $buildable = $build->getBuildable();
    $object = $buildable->getBuildableObject();

    $repo = null;
    if ($object instanceof DifferentialRevision) {
      $results['revision'] = $object->getID();
      $repo = $object->getRepository();
    } else if ($object instanceof PhabricatorRepositoryCommit) {
      $results['commit'] = $object->getCommitIdentifier();
      $repo = $object->getRepository();
    }

    $results['repository'] = $repo->getCallsign();
    $results['vcs'] = $repo->getVersionControlSystem();
    $results['uri'] = $repo->getPublicRemoteURI();
    $results['timestamp'] = time();

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
      'revision' => pht('The differential revision ID, if applicable.'),
      'commit' => pht('The commit identifier, if applicable.'),
      'repository' => pht('The callsign of the repository in Phabricator.'),
      'vcs' => pht('The version control system, either "svn", "hg" or "git".'),
      'uri' => pht('The URI to clone or checkout the repository from.'),
      'timestamp' => pht('The current UNIX timestamp.'));
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
