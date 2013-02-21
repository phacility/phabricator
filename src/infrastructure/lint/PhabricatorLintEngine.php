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

          'javelin_render_tag' =>
            'The javelin_render_tag() function is deprecated and unsafe. '.
            'Use javelin_tag() instead.',

          'phabricator_render_form' =>
            'The phabricator_render_form() function is deprecated and unsafe. '.
            'Use phabricator_form() instead.',
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

    foreach ($paths as $path) {
      if (strpos($path, 'support/aphlict/') !== false) {
        // This stuff is Node.js, not Javelin, so don't apply the Javelin
        // linter.
        continue;
      }
      if (preg_match('/\.js$/', $path)) {
        $javelin_linter->addPath($path);
        $javelin_linter->addData($path, $this->loadData($path));
      }
    }

    return $linters;
  }

}
