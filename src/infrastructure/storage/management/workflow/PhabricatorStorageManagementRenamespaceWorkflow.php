<?php

final class PhabricatorStorageManagementRenamespaceWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('renamespace')
      ->setExamples(
        '**renamespace** [__options__] '.
        '--in __dump.sql__ --from __old__ --to __new__ > __out.sql__')
      ->setSynopsis(pht('Change the database namespace of a .sql dump file.'))
      ->setArguments(
        array(
          array(
            'name' => 'in',
            'param' => 'file',
            'help' => pht('SQL dumpfile to process.'),
          ),
          array(
            'name' => 'from',
            'param' => 'namespace',
            'help' => pht('Current database namespace used by dumpfile.'),
          ),
          array(
            'name' => 'to',
            'param' => 'namespace',
            'help' => pht('Desired database namespace for output.'),
          ),
        ));
  }

  public function didExecute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $in = $args->getArg('in');
    if (!strlen($in)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify the dumpfile to read with %s.',
          '--in'));
    }

    $from = $args->getArg('from');
    if (!strlen($from)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify namespace to rename from with %s.',
          '--from'));
    }

    $to = $args->getArg('to');
    if (!strlen($to)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify namespace to rename to with %s.',
          '--to'));
    }

    $patterns = array(
      'use' => '@^(USE `)([^_]+)(_.*)$@',
      'create' => '@^(CREATE DATABASE /\*.*?\*/ `)([^_]+)(_.*)$@',
    );

    $found = array_fill_keys(array_keys($patterns), 0);

    $matches = null;
    foreach (new LinesOfALargeFile($in) as $line) {

      foreach ($patterns as $key => $pattern) {
        if (preg_match($pattern, $line, $matches)) {
          $namespace = $matches[2];
          if ($namespace != $from) {
            throw new Exception(
              pht(
                'Expected namespace "%s", found "%s": %s.',
                $from,
                $namespace,
                $line));
          }

          $line = $matches[1].$to.$matches[3];
          $found[$key]++;
        }
      }

      echo $line."\n";
    }

    // Give the user a chance to catch things if the results are crazy.
    $console->writeErr(
      pht(
        'Adjusted **%s** create statements and **%s** use statements.',
        new PhutilNumber($found['create']),
        new PhutilNumber($found['use']))."\n");

    return 0;
  }

}
