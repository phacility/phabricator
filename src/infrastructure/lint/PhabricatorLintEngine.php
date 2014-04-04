<?php

/**
 * @concrete-extensible
 */
class PhabricatorLintEngine extends PhutilLintEngine {

  public function buildLinters() {
    $linters = parent::buildLinters();

    foreach ($linters as $linter) {
      if ($linter instanceof ArcanistPhutilXHPASTLinter) {
        $linter->setDeprecatedFunctions(array(
          'phutil_escape_html' =>
            'The phutil_escape_html() function is deprecated. Raw strings '.
            'passed to phutil_tag() or hsprintf() are escaped automatically.',
        ));
      }
    }

    $paths = $this->getPaths();

    foreach ($paths as $key => $path) {
      if (!$this->pathExists($path)) {
        unset($paths[$key]);
      }
    }

    $javelin_linter = new PhabricatorJavelinLinter();
    $linters[] = $javelin_linter;

    $jshint_linter = new ArcanistJSHintLinter();
    $linters[] = $jshint_linter;

    foreach ($paths as $path) {
      if (!preg_match('/\.js$/', $path)) {
        continue;
      }

      if (strpos($path, 'externals/JsShrink') !== false) {
        // Ignore warnings in JsShrink tests.
        continue;
      }

      if (strpos($path, 'externals/raphael') !== false) {
        // Ignore Raphael.
        continue;
      }

      $jshint_linter->addPath($path);
      $jshint_linter->addData($path, $this->loadData($path));

      if (strpos($path, 'support/aphlict/') !== false) {
        // This stuff is Node.js, not Javelin, so don't apply the Javelin
        // linter.
        continue;
      }

      $javelin_linter->addPath($path);
      $javelin_linter->addData($path, $this->loadData($path));
    }

    return $linters;
  }

}
