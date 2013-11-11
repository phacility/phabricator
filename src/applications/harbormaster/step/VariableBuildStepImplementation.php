<?php

abstract class VariableBuildStepImplementation extends BuildStepImplementation {

  public function retrieveVariablesFromBuild(HarbormasterBuild $build) {
    $results = array(
      'buildable.diff' => null,
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
    if ($object instanceof DifferentialDiff) {
      $results['buildable.diff'] = $object->getID();
      $revision = $object->getRevision();
      $results['buildable.revision'] = $revision->getID();
      $repo = $revision->getRepository();
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


  /**
   * Convert a user-provided string with variables in it, like:
   *
   *   ls ${dirname}
   *
   * ...into a string with variables merged into it safely:
   *
   *   ls 'dir with spaces'
   *
   * @param string Name of a `vxsprintf` function, like @{function:vcsprintf}.
   * @param string User-provided pattern string containing `${variables}`.
   * @param dict   List of available replacement variables.
   * @return string String with variables replaced safely into it.
   */
  protected function mergeVariables($function, $pattern, array $variables) {
    $regexp = '/\\$\\{(?P<name>[a-z\\.]+)\\}/';

    $matches = null;
    preg_match_all($regexp, $pattern, $matches);

    $argv = array();
    foreach ($matches['name'] as $name) {
      if (!array_key_exists($name, $variables)) {
        throw new Exception(pht("No such variable '%s'!", $name));
      }
      $argv[] = $variables[$name];
    }

    $pattern = str_replace('%', '%%', $pattern);
    $pattern = preg_replace($regexp, '%s', $pattern);

    return call_user_func($function, $pattern, $argv);
  }


  public function getAvailableVariables() {
    return array(
      'buildable.diff' =>
        pht('The differential diff ID, if applicable.'),
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
