#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setSynopsis(<<<EOSYNOPSIS
**import_repository_symbols.php** [__options__] __repository__ < symbols

  Import repository symbols (symbols are read from stdin).
EOSYNOPSIS
  );
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'      => 'no-purge',
      'help'      => pht(
        'Do not clear all symbols for this repository before '.
        'uploading new symbols. Useful for incremental updating.'),
    ),
    array(
      'name'      => 'ignore-errors',
      'help'      => pht(
        "If a line can't be parsed, ignore that line and ".
        "continue instead of exiting."),
    ),
    array(
      'name'      => 'max-transaction',
      'param'     => 'num-syms',
      'default'   => '100000',
      'help'      => pht(
        'Maximum number of symbols that should '.
        'be part of a single transaction.'),
    ),
    array(
      'name'      => 'repository',
      'wildcard'  => true,
    ),
  ));

$identifiers = $args->getArg('repository');
if (count($identifiers) !== 1) {
  $args->printHelpAndExit();
}

$identifier = head($identifiers);
$repository = id(new PhabricatorRepositoryQuery())
  ->setViewer(PhabricatorUser::getOmnipotentUser())
  ->withIdentifiers($identifiers)
  ->executeOne();

if (!$repository) {
  echo tsprintf(
    "%s\n",
    pht('Repository "%s" does not exist.', $identifier));
  exit(1);
}

if (!function_exists('posix_isatty') || posix_isatty(STDIN)) {
  echo pht('Parsing input from stdin...'), "\n";
}

$input = file_get_contents('php://stdin');
$input = trim($input);
$input = explode("\n", $input);


function commit_symbols(
  array $symbols,
  PhabricatorRepository $repository,
  $no_purge) {

  echo pht('Looking up path IDs...'), "\n";
  $path_map =
    PhabricatorRepositoryCommitChangeParserWorker::lookupOrCreatePaths(
      ipull($symbols, 'path'));

  $symbol = new PhabricatorRepositorySymbol();
  $conn_w = $symbol->establishConnection('w');

  echo pht('Preparing queries...'), "\n";
  $sql = array();
  foreach ($symbols as $dict) {
    $sql[] = qsprintf(
      $conn_w,
      '(%s, %s, %s, %s, %s, %d, %d)',
      $repository->getPHID(),
      $dict['ctxt'],
      $dict['name'],
      $dict['type'],
      $dict['lang'],
      $dict['line'],
      $path_map[$dict['path']]);
  }

  if (!$no_purge) {
    echo pht('Purging old symbols...'), "\n";
    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE repositoryPHID = %s',
      $symbol->getTableName(),
      $repository->getPHID());
  }

  echo pht('Loading %s symbols...',  phutil_count($sql)), "\n";
  foreach (array_chunk($sql, 128) as $chunk) {
    queryfx(
      $conn_w,
      'INSERT INTO %T
        (repositoryPHID, symbolContext, symbolName, symbolType,
        symbolLanguage, lineNumber, pathID) VALUES %LQ',
      $symbol->getTableName(),
      $chunk);
  }
}

function check_string_value($value, $field_name, $line_no, $max_length) {
   if (strlen($value) > $max_length) {
      throw new Exception(
        pht(
          "%s '%s' defined on line #%d is too long, ".
          "maximum %s length is %d characters.",
          $field_name,
          $value,
          $line_no,
          $field_name,
          $max_length));
    }

    if (!phutil_is_utf8_with_only_bmp_characters($value)) {
      throw new Exception(
        pht(
          "%s '%s' defined on line #%d is not a valid ".
          "UTF-8 string, it should contain only UTF-8 characters.",
          $field_name,
          $value,
          $line_no));
    }
}

$no_purge = $args->getArg('no-purge');
$symbols = array();
foreach ($input as $key => $line) {
  try {
    $line_no = $key + 1;
    $matches = null;
    $ok = preg_match(
      '/^((?P<context>[^ ]+)? )?(?P<name>[^ ]+) (?P<type>[^ ]+) '.
      '(?P<lang>[^ ]+) (?P<line>\d+) (?P<path>.*)$/',
      $line,
      $matches);
    if (!$ok) {
      throw new Exception(
        pht(
          "Line #%d of input is invalid. Expected five or six space-delimited ".
          "fields: maybe symbol context, symbol name, symbol type, symbol ".
          "language, line number, path. For example:\n\n%s\n\n".
          "Actual line was:\n\n%s",
          $line_no,
          'idx function php 13 /path/to/some/file.php',
          $line));
    }
    if (empty($matches['context'])) {
      $matches['context'] = '';
    }
    $context     = $matches['context'];
    $name        = $matches['name'];
    $type        = $matches['type'];
    $lang        = $matches['lang'];
    $line_number = $matches['line'];
    $path        = $matches['path'];

    check_string_value($context, pht('Symbol context'), $line_no, 128);
    check_string_value($name, pht('Symbol name'), $line_no, 128);
    check_string_value($type, pht('Symbol type'), $line_no, 12);
    check_string_value($lang, pht('Symbol language'), $line_no, 32);
    check_string_value($path, pht('Path'), $line_no, 512);

    if (!strlen($path) || $path[0] != '/') {
      throw new Exception(
        pht(
          "Path '%s' defined on line #%d is invalid. Paths should begin with ".
          "'%s' and specify a path from the root of the project, like '%s'.",
          $path,
          $line_no,
          '/',
          '/src/utils/utils.php'));
    }

    $symbols[] = array(
      'ctxt' => $context,
      'name' => $name,
      'type' => $type,
      'lang' => $lang,
      'line' => $line_number,
      'path' => $path,
    );
  } catch (Exception $e) {
    if ($args->getArg('ignore-errors')) {
      continue;
    } else {
      throw $e;
    }
  }

  if (count($symbols) >= $args->getArg('max-transaction')) {
    try {
      echo pht(
        "Committing %s symbols...\n",
        new PhutilNumber($args->getArg('max-transaction')));
      commit_symbols($symbols, $repository, $no_purge);
      $no_purge = true;
      unset($symbols);
      $symbols = array();
    } catch (Exception $e) {
      if ($args->getArg('ignore-errors')) {
        continue;
      } else {
        throw $e;
      }
    }
  }
}

if (count($symbols)) {
  commit_symbols($symbols, $repository, $no_purge);
}

echo pht('Done.')."\n";
