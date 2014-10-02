<?php

final class PhabricatorStorageManagementQuickstartWorkflow
  extends PhabricatorStorageManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('quickstart')
      ->setExamples('**quickstart** [__options__]')
      ->setSynopsis(
        pht(
          'Generate a new quickstart database dump. This command is mostly '.
          'useful when developing Phabricator.'))
      ->setArguments(
        array(
          array(
            'name'  => 'output',
            'param' => 'file',
            'help'  => pht('Specify output file to write.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $output = $args->getArg('output');
    if (!$output) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a file to write with `--output`.'));
    }

    $namespace = 'phabricator_quickstart_'.Filesystem::readRandomCharacters(8);

    $bin = dirname(phutil_get_library_root('phabricator')).'/bin/storage';

    $err = phutil_passthru(
      '%s upgrade --force --no-quickstart --namespace %s',
      $bin,
      $namespace);
    if ($err) {
      return $err;
    }

    $err = phutil_passthru(
      '%s adjust --force --namespace %s',
      $bin,
      $namespace);
    if ($err) {
      return $err;
    }

    $tmp = new TempFile();
    $err = phutil_passthru(
      '%s dump --namespace %s > %s',
      $bin,
      $namespace,
      $tmp);
    if ($err) {
      return $err;
    }

    $err = phutil_passthru(
      '%s destroy --force --namespace %s',
      $bin,
      $namespace);
    if ($err) {
      return $err;
    }

    $dump = Filesystem::readFile($tmp);

    $dump = str_replace(
      $namespace,
      '{$NAMESPACE}',
      $dump);

    $dump = str_replace(
      'utf8mb4_bin',
      '{$COLLATE_TEXT}',
      $dump);

    $dump = str_replace(
      'utf8mb4_unicode_ci',
      '{$COLLATE_SORT}',
      $dump);

    $dump = str_replace(
      'utf8mb4',
      '{$CHARSET}',
      $dump);

    // Strip out a bunch of unnecessary commands which make the dump harder
    // to handle and slower to import.

    // Remove character set adjustments and key disables.
    $dump = preg_replace(
      '(^/\*.*\*/;$)m',
      '',
      $dump);

    // Remove comments.
    $dump = preg_replace('/^--.*$/m', '', $dump);

    // Remove table drops, locks, and unlocks. These are never relevant when
    // performing q quickstart.
    $dump = preg_replace(
      '/^(DROP TABLE|LOCK TABLES|UNLOCK TABLES).*$/m',
      '',
      $dump);

    // Collapse adjacent newlines.
    $dump = preg_replace('/\n\s*\n/', "\n", $dump);

    $dump = str_replace(';', ";\n", $dump);
    $dump = trim($dump)."\n";

    Filesystem::writeFile($output, $dump);

    return 0;
  }

}
