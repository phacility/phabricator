<?php

final class PhabricatorInternationalizationManagementExtractWorkflow
  extends PhabricatorInternationalizationManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('extract')
      ->setSynopsis(pht('Extract translatable strings.'))
      ->setArguments(
        array(
          array(
            'name' => 'paths',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $paths = $args->getArg('paths');

    $futures = array();
    foreach ($paths as $path) {
      $root = Filesystem::resolvePath($path);
      $path_files = id(new FileFinder($root))
        ->withType('f')
        ->withSuffix('php')
        ->find();

      foreach ($path_files as $file) {
        $full_path = $root.DIRECTORY_SEPARATOR.$file;
        $data = Filesystem::readFile($full_path);
        $futures[$full_path] = PhutilXHPASTBinary::getParserFuture($data);
      }
    }

    $console->writeErr(
      "%s\n",
      pht('Found %s file(s)...', phutil_count($futures)));

    $results = array();

    $bar = id(new PhutilConsoleProgressBar())
      ->setTotal(count($futures));

    $messages = array();

    $futures = id(new FutureIterator($futures))
      ->limit(8);
    foreach ($futures as $full_path => $future) {
      $bar->update(1);

      try {
        $tree = XHPASTTree::newFromDataAndResolvedExecFuture(
          Filesystem::readFile($full_path),
          $future->resolve());
      } catch (Exception $ex) {
        $messages[] = pht(
          'WARNING: Failed to extract strings from file "%s": %s',
          $full_path,
          $ex->getMessage());
        continue;
      }

      $root = $tree->getRootNode();
      $calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
      foreach ($calls as $call) {
        $name = $call->getChildByIndex(0)->getConcreteString();
        if ($name == 'pht') {
          $params = $call->getChildByIndex(1, 'n_CALL_PARAMETER_LIST');
          $string_node = $params->getChildByIndex(0);
          $string_line = $string_node->getLineNumber();
          try {
            $string_value = $string_node->evalStatic();

            $results[$string_value][] = array(
              'file' => Filesystem::readablePath($full_path),
              'line' => $string_line,
            );
          } catch (Exception $ex) {
            $messages[] = pht(
              'WARNING: Failed to evaluate pht() call on line %d in "%s": %s',
              $call->getLineNumber(),
              $full_path,
              $ex->getMessage());
          }
        }
      }

      $tree->dispose();
    }
    $bar->done();

    foreach ($messages as $message) {
      $console->writeErr("%s\n", $message);
    }

    ksort($results);

    $out = array();
    $out[] = '<?php';
    $out[] = '// @no'.'lint';
    $out[] = 'return array(';
    foreach ($results as $string => $locations) {
      foreach ($locations as $location) {
        $out[] = '  // '.$location['file'].':'.$location['line'];
      }
      $out[] = "  '".addcslashes($string, "\0..\37\\'\177..\377")."' => null,";
      $out[] = null;
    }
    $out[] = ');';
    $out[] = null;

    echo implode("\n", $out);

    return 0;
  }

}
