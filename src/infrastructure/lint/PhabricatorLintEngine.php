<?php

final class PhabricatorLintEngine extends PhutilLintEngine {

  public function buildLinters() {
    $linters = parent::buildLinters();

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
