#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setSynopsis(<<<EOSYNOPSIS
**generate_php_symbols.php** [__options__]

  Generate repository symbols using XHPAST. Paths are read from stdin.
EOSYNOPSIS
  );
$args->parseStandardArguments();

if (posix_isatty(STDIN)) {
  echo phutil_console_format(
    "%s\n",
    pht(
      'Usage: %s',
      "find . -type f -name '*.php' | ./generate_php_symbols.php"));
  exit(1);
}

$input = file_get_contents('php://stdin');
$data = array();
$futures = array();

foreach (explode("\n", trim($input)) as $file) {
  if (!strlen($file)) {
    continue;
  }

  $file = Filesystem::readablePath($file);
  $data[$file] = Filesystem::readFile($file);
  $futures[$file] = PhutilXHPASTBinary::getParserFuture($data[$file]);
}

$futures = new FutureIterator($futures);
foreach ($futures->limit(8) as $file => $future) {
  $tree = XHPASTTree::newFromDataAndResolvedExecFuture(
    $data[$file],
    $future->resolve());

  $root = $tree->getRootNode();
  $scopes = array();

  $functions = $root->selectDescendantsOfType('n_FUNCTION_DECLARATION');
  foreach ($functions as $function) {
    $name = $function->getChildByIndex(2);
    // Skip anonymous functions.
    if (!$name->getConcreteString()) {
      continue;
    }
    print_symbol($file, 'function', $name);
  }

  $classes = $root->selectDescendantsOfType('n_CLASS_DECLARATION');
  foreach ($classes as $class) {
    $class_name = $class->getChildByIndex(1);
    print_symbol($file, 'class', $class_name);
    $scopes[] = array($class, $class_name);
  }

  $interfaces = $root->selectDescendantsOfType('n_INTERFACE_DECLARATION');
  foreach ($interfaces as $interface) {
    $interface_name = $interface->getChildByIndex(1);
    // We don't differentiate classes and interfaces in highlighters.
    print_symbol($file, 'class', $interface_name);
    $scopes[] = array($interface, $interface_name);
  }

  $constants = $root->selectDescendantsOfType('n_CONSTANT_DECLARATION_LIST');
  foreach ($constants as $constant_list) {
    foreach ($constant_list->getChildren() as $constant) {
      $constant_name = $constant->getChildByIndex(0);
      print_symbol($file, 'constant', $constant_name);
    }
  }

  foreach ($scopes as $scope) {
    // This prints duplicate symbols in the case of nested classes.
    // Luckily, PHP doesn't allow those.
    list($class, $class_name) = $scope;

    $consts = $class->selectDescendantsOfType(
      'n_CLASS_CONSTANT_DECLARATION_LIST');
    foreach ($consts as $const_list) {
      foreach ($const_list->getChildren() as $const) {
        $const_name = $const->getChildByIndex(0);
        print_symbol($file, 'class_const', $const_name, $class_name);
      }
    }

    $members = $class->selectDescendantsOfType(
      'n_CLASS_MEMBER_DECLARATION_LIST');
    foreach ($members as $member_list) {
      foreach ($member_list->getChildren() as $member) {
        if ($member->getTypeName() == 'n_CLASS_MEMBER_MODIFIER_LIST') {
          continue;
        }
        $member_name = $member->getChildByIndex(0);
        print_symbol($file, 'member', $member_name, $class_name);
      }
    }

    $methods = $class->selectDescendantsOfType('n_METHOD_DECLARATION');
    foreach ($methods as $method) {
      $method_name = $method->getChildByIndex(2);
      print_symbol($file, 'method', $method_name, $class_name);
    }
  }
}

function print_symbol($file, $type, XHPASTNode $node, $context = null) {
  $parts = array(
    $context ? $context->getConcreteString() : '',
    // Variable tokens are `$name`, not just `name`, so strip the "$"" off of
    // class field names
    ltrim($node->getConcreteString(), '$'),
    $type,
    'php',
    $node->getLineNumber(),
    '/'.ltrim($file, './'),
  );
  echo implode(' ', $parts)."\n";
}
