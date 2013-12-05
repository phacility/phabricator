<?php

abstract class VariableBuildStepImplementation extends BuildStepImplementation {

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

  public function getSettingRemarkupInstructions() {
    $variables = HarbormasterBuild::getAvailableBuildVariables();
    $text = '';
    $text .= pht('The following variables are available: ')."\n";
    $text .= "\n";
    foreach ($variables as $name => $desc) {
      $text .= '  - `'.$name.'`: '.$desc."\n";
    }
    $text .= "\n";
    $text .= "Use `\${name}` to merge a variable into a setting.";
    return $text;
  }

}
