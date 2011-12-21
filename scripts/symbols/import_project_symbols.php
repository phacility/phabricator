#!/usr/bin/env php
<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

phutil_require_module('phutil', 'console');

if ($argc !== 2) {
  echo phutil_console_format(
    "usage: import_project_symbols.php __project_name__ < __symbol_file__\n");
  exit(1);
}

$project_name = $argv[1];
$project = id(new PhabricatorRepositoryArcanistProject())->loadOneWhere(
  'name = %s',
  $project_name);

if (!$project) {
  // TODO: Provide a less silly way to do this explicitly, or just do it right
  // here.
  echo "Project '{$project_name}' is unknown. Upload a diff to implicitly ".
       "create it.\n";
  exit(1);
}

echo "Parsing input from stdin...\n";
$input = file_get_contents('php://stdin');
$input = trim($input);
$input = explode("\n", $input);

$map = array();
$symbols = array();
foreach ($input as $key => $line) {
  $line_no = $key + 1;
  $matches = null;
  $ok = preg_match('/^([^ ]+) ([^ ]+) ([^ ]+) (\d+) (.*)$/', $line, $matches);
  if (!$ok) {
    throw new Exception(
      "Line #{$line_no} of input is invalid. Expected five space-delimited ".
      "fields: symbol name, symbol type, symbol language, line number, path. ".
      "For example:\n\n".
      "idx function php 13 /path/to/some/file.php\n\n".
      "Actual line was:\n\n".
      "{$line}");
  }
  list($all, $name, $type, $lang, $line_number, $path) = $matches;

  if (isset($map[$name][$type][$lang])) {
    $previous = $map[$name][$type][$lang] + 1;
    throw new Exception(
      "Line #{$line_no} of input is invalid. It specifies a duplicate symbol ".
      "(same name, language, and type) which has already been defined ".
      "elsewhere. You must preprocess the symbol list to remove duplicates ".
      "and choose exactly one master definition for each symbol. This symbol ".
      "was previously defined on line #{$previous}.\n\n".
      "Line #{$line_no}:\n".
      $line."\n\n".
      "Line #{$previous}:\n".
      $input[$previous - 1]);
  } else {
    $map[$name][$type][$lang] = $key;
  }

  if (strlen($name) > 128) {
    throw new Exception(
      "Symbol name '{$name}' defined on line #{$line_no} is too long, maximum ".
      "symbol name length is 128 characters.");
  }

  if (strlen($type) > 12) {
    throw new Exception(
      "Symbol type '{$type}' defined on line #{$line_no} is too long, maximum ".
      "symbol type length is 12 characters.");
  }

  if (strlen($lang) > 32) {
    throw new Exception(
      "Symbol language '{$lang}' defined on line #{$line_no} is too long, ".
      "maximum symbol language length is 32 characters.");
  }

  if (!strlen($path) || $path[0] != 0) {
    throw new Exception(
      "Path '{$path}' defined on line #{$line_no} is invalid. Paths should be ".
      "begin with '/' and specify a path from the root of the project, like ".
      "'/src/utils/utils.php'.");
  }

  $symbols[] = array(
    'name' => $name,
    'type' => $type,
    'lang' => $lang,
    'line' => $line_number,
    'path' => $path,
  );
}

echo "Looking up path IDs...\n";
$path_map = PhabricatorRepositoryCommitChangeParserWorker::lookupOrCreatePaths(
  ipull($symbols, 'path'));

$symbol = new PhabricatorRepositorySymbol();
$conn_w = $symbol->establishConnection('w');

echo "Preparing queries...\n";
$sql = array();
foreach ($symbols as $dict) {
  $sql[] = qsprintf(
    $conn_w,
    '(%d, %s, %s, %s, %d, %d)',
    $project->getID(),
    $dict['name'],
    $dict['type'],
    $dict['lang'],
    $dict['line'],
    $path_map[$dict['path']]);
}

echo "Purging old symbols...\n";
queryfx(
  $conn_w,
  'DELETE FROM %T WHERE arcanistProjectID = %d',
  $symbol->getTableName(),
  $project->getID());

echo "Loading ".number_format(count($sql))." symbols...\n";
foreach (array_chunk($sql, 128) as $chunk) {
  queryfx(
    $conn_w,
    'INSERT INTO %T
      (arcanistProjectID, symbolName, symbolType, symbolLanguage, lineNumber,
        pathID) VALUES %Q',
    $symbol->getTableName(),
    implode(', ', $chunk));
}

echo "Done.\n";
