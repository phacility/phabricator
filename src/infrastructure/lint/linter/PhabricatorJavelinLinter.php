<?php

final class PhabricatorJavelinLinter extends ArcanistLinter {

  private $symbols = array();

  private $symbolsBinary;
  private $haveWarnedAboutBinary;

  const LINT_PRIVATE_ACCESS = 1;
  const LINT_MISSING_DEPENDENCY = 2;
  const LINT_UNNECESSARY_DEPENDENCY = 3;
  const LINT_UNKNOWN_DEPENDENCY = 4;
  const LINT_MISSING_BINARY = 5;

  public function getInfoName() {
    return pht('Javelin Linter');
  }

  public function getInfoDescription() {
    return pht(
      'This linter is intended for use with the Javelin JS library and '.
      'extensions. Use `%s` to run Javelin rules on Javascript source files.',
      'javelinsymbols');
  }

  private function getBinaryPath() {
    if ($this->symbolsBinary === null) {
      list($err, $stdout) = exec_manual('which javelinsymbols');
      $this->symbolsBinary = ($err ? false : rtrim($stdout));
    }
    return $this->symbolsBinary;
  }

  public function willLintPaths(array $paths) {
    if (!$this->getBinaryPath()) {
      return;
    }

    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/scripts/__init_script__.php';

    $futures = array();
    foreach ($paths as $path) {
      if ($this->shouldIgnorePath($path)) {
        continue;
      }

      $future = $this->newSymbolsFuture($path);
      $futures[$path] = $future;
    }

    foreach (id(new FutureIterator($futures))->limit(8) as $path => $future) {
      $this->symbols[$path] = $future->resolvex();
    }
  }

  public function getLinterName() {
    return 'JAVELIN';
  }

  public function getLinterConfigurationName() {
    return 'javelin';
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_MISSING_BINARY => ArcanistLintSeverity::SEVERITY_WARNING,
    );
  }

  public function getLintNameMap() {
    return array(
      self::LINT_PRIVATE_ACCESS =>
        pht('Private Method/Member Access'),
      self::LINT_MISSING_DEPENDENCY =>
        pht('Missing Javelin Dependency'),
      self::LINT_UNNECESSARY_DEPENDENCY =>
        pht('Unnecessary Javelin Dependency'),
      self::LINT_UNKNOWN_DEPENDENCY =>
        pht('Unknown Javelin Dependency'),
      self::LINT_MISSING_BINARY =>
        pht('`%s` Not In Path', 'javelinsymbols'),
    );
  }

  public function getCacheGranularity() {
    return parent::GRANULARITY_REPOSITORY;
  }

  public function getCacheVersion() {
    $version = '0';
    $binary_path = $this->getBinaryPath();
    if ($binary_path) {
      $version .= '-'.md5_file($binary_path);
    }
    return $version;
  }

  private function shouldIgnorePath($path) {
    return preg_match('@/__tests__/|externals/javelin/docs/@', $path);
  }

  public function lintPath($path) {
    if ($this->shouldIgnorePath($path)) {
      return;
    }

    if (!$this->symbolsBinary) {
      if (!$this->haveWarnedAboutBinary) {
        $this->haveWarnedAboutBinary = true;
        // TODO: Write build documentation for the Javelin binaries and point
        // the user at it.
        $this->raiseLintAtLine(
          1,
          0,
          self::LINT_MISSING_BINARY,
          pht(
            "The '%s' binary in the Javelin project is not available in %s, ".
            "so the Javelin linter can't run. This isn't a big concern, ".
            "but means some Javelin problems can't be automatically detected.",
            'javelinsymbols',
            '$PATH'));
      }
      return;
    }

    list($uses, $installs) = $this->getUsedAndInstalledSymbolsForPath($path);
    foreach ($uses as $symbol => $line) {
      $parts = explode('.', $symbol);
      foreach ($parts as $part) {
        if ($part[0] == '_' && $part[1] != '_') {
          $base = implode('.', array_slice($parts, 0, 2));
          if (!array_key_exists($base, $installs)) {
            $this->raiseLintAtLine(
              $line,
              0,
              self::LINT_PRIVATE_ACCESS,
              pht(
                "This file accesses private symbol '%s' across file ".
                "boundaries. You may only access private members and methods ".
                "from the file where they are defined.",
                $symbol));
          }
          break;
        }
      }
    }

    $external_classes = array();
    foreach ($uses as $symbol => $line) {
      $parts = explode('.', $symbol);
      $class = implode('.', array_slice($parts, 0, 2));
      if (!array_key_exists($class, $external_classes) &&
          !array_key_exists($class, $installs)) {
        $external_classes[$class] = $line;
      }
    }

    $celerity = CelerityResourceMap::getNamedInstance('phabricator');

    $path = preg_replace(
      '@^externals/javelinjs/src/@',
      'webroot/rsrc/js/javelin/',
      $path);
    $need = $external_classes;

    $resource_name = substr($path, strlen('webroot/'));
    $requires = $celerity->getRequiredSymbolsForName($resource_name);
    if (!$requires) {
      $requires = array();
    }

    foreach ($requires as $key => $requires_symbol) {
      $requires_name = $celerity->getResourceNameForSymbol($requires_symbol);
      if ($requires_name === null) {
        $this->raiseLintAtLine(
          0,
          0,
          self::LINT_UNKNOWN_DEPENDENCY,
          pht(
            "This file %s component '%s', but it does not exist. ".
            "You may need to rebuild the Celerity map.",
            '@requires',
            $requires_symbol));
        unset($requires[$key]);
        continue;
      }

      if (preg_match('/\\.css$/', $requires_name)) {
        // If JS requires CSS, just assume everything is fine.
        unset($requires[$key]);
      } else {
        $symbol_path = 'webroot/'.$requires_name;
        list($ignored, $req_install) = $this->getUsedAndInstalledSymbolsForPath(
          $symbol_path);
        if (array_intersect_key($req_install, $external_classes)) {
          $need = array_diff_key($need, $req_install);
          unset($requires[$key]);
        }
      }
    }

    foreach ($need as $class => $line) {
      $this->raiseLintAtLine(
        $line,
        0,
        self::LINT_MISSING_DEPENDENCY,
        pht(
          "This file uses '%s' but does not @requires the component ".
          "which installs it. You may need to rebuild the Celerity map.",
          $class));
    }

    foreach ($requires as $component) {
      $this->raiseLintAtLine(
        0,
        0,
        self::LINT_UNNECESSARY_DEPENDENCY,
        pht(
          "This file %s component '%s' but does not use anything it provides.",
          '@requires',
          $component));
    }
  }

  private function loadSymbols($path) {
    if (empty($this->symbols[$path])) {
      $this->symbols[$path] = $this->newSymbolsFuture($path)->resolvex();
    }
    return $this->symbols[$path];
  }

  private function newSymbolsFuture($path) {
    $future = new ExecFuture('javelinsymbols # %s', $path);
    $future->write($this->getData($path));
    return $future;
  }

  private function getUsedAndInstalledSymbolsForPath($path) {
    list($symbols) = $this->loadSymbols($path);
    $symbols = trim($symbols);

    $uses = array();
    $installs = array();
    if (empty($symbols)) {
      // This file has no symbols.
      return array($uses, $installs);
    }

    $symbols = explode("\n", trim($symbols));
    foreach ($symbols as $line) {
      $matches = null;
      if (!preg_match('/^([?+\*])([^:]*):(\d+)$/', $line, $matches)) {
        throw new Exception(
          pht('Received malformed output from `%s`.', 'javelinsymbols'));
      }
      $type = $matches[1];
      $symbol = $matches[2];
      $line = $matches[3];

      switch ($type) {
        case '?':
          $uses[$symbol] = $line;
          break;
        case '+':
          $installs['JX.'.$symbol] = $line;
          break;
      }
    }

    $contents = $this->getData($path);

    $matches = null;
    $count = preg_match_all(
      '/@javelin-installs\W+(\S+)/',
      $contents,
      $matches,
      PREG_PATTERN_ORDER);

    if ($count) {
      foreach ($matches[1] as $symbol) {
        $installs[$symbol] = 0;
      }
    }

    return array($uses, $installs);
  }

}
