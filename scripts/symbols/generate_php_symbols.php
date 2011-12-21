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
phutil_require_module('phutil', 'parser/xhpast/bin');

if ($argc !== 1 || posix_isatty(STDIN)) {
  echo phutil_console_format(
    "usage: find . -type f -name '*.php' | ./generate_php_symbols.php\n");
  exit(1);
}

$input = file_get_contents('php://stdin');
$input = trim($input);
$input = explode("\n", $input);

$data = array();
$futures = array();

foreach ($input as $file) {
  $file = Filesystem::readablePath($file);
  $data[$file] = Filesystem::readFile($file);
  $futures[$file] = xhpast_get_parser_future($data[$file]);
}

foreach (Futures($futures)->limit(8) as $file => $future) {
  $tree = XHPASTTree::newFromDataAndResolvedExecFuture(
    $data[$file],
    $future->resolve());

  $root = $tree->getRootNode();

  $functions = $root->selectDescendantsOfType('n_FUNCTION_DECLARATION');
  foreach ($functions as $function) {
    $name = $function->getChildByIndex(2);
    print_symbol($file, 'function', $name);
  }

  $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
  foreach ($classes as $class) {
    $class_name = $class->getChildByIndex(1);
    print_symbol($file, 'class', $class_name);
  }

  $interfaces = $root->selectDescendantsOfType('n_INTERFACE_DECLARATION');
  foreach ($interfaces as $interface) {
    $interface_name = $interface->getChildByIndex(1);
    print_symbol($file, 'interface', $interface_name);
  }
}

function print_symbol($file, $type, $token) {
  $parts = array(
    $token->getConcreteString(),
    $type,
    'php',
    $token->getLineNumber(),
    '/'.ltrim($file, './'),
  );
  echo implode(' ', $parts)."\n";
}
