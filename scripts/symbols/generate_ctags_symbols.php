#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setSynopsis(<<<EOSYNOPSIS
**generate_ctags_symbols.php** [__options__]

  Generate repository symbols using Exuberant Ctags. Paths are read from stdin.
EOSYNOPSIS
  );
$args->parseStandardArguments();

if (ctags_check_executable() == false) {
  echo phutil_console_format(
    "%s\n\n%s\n",
    pht(
      'Could not find Exuberant Ctags. Make sure it is installed and '.
      'available in executable path.'),
    pht(
      'Exuberant Ctags project page: %s',
      'http://ctags.sourceforge.net/'));
  exit(1);
}

if (posix_isatty(STDIN)) {
  echo phutil_console_format(
    "%s\n",
    pht(
      'Usage: %s',
      "find . -type f -name '*.py' | ./generate_ctags_symbols.php"));
  exit(1);
}

$input = file_get_contents('php://stdin');
$data = array();
$futures = array();

foreach (explode("\n", trim($input)) as $file) {
  $file = Filesystem::readablePath($file);
  $futures[$file] = ctags_get_parser_future($file);
}

$futures = new FutureIterator($futures);
foreach ($futures->limit(8) as $file => $future) {
  $tags = $future->resolve();
  $tags = explode("\n", $tags[1]);

  foreach ($tags as $tag) {
    $parts = explode(';', $tag);

    // Skip lines that we can not parse.
    if (count($parts) < 2) {
      continue;
    }

    // Split ctags information.
    $tag_info = explode("\t", $parts[0]);

    // Split exuberant ctags "extension fields" (additional information).
    $parts[1] = trim($parts[1], "\t \"");
    $extension_fields = explode("\t", $parts[1]);

    // Skip lines that we can not parse.
    if (count($tag_info) < 3 || count($extension_fields) < 2) {
      continue;
    }

    // Default context to empty.
    $extension_fields[] = '';
    list($token, $file_path, $line_num) = $tag_info;
    list($type, $language, $context) = $extension_fields;

    // Skip lines with tokens containing a space.
    if (strpos($token, ' ') !== false) {
      continue;
    }

    // Strip "language:"
    $language = substr($language, 9);

    // To keep consistent with "Separate with commas, for example: php, py"
    // in Arcanist Project edit form.
    $language = str_ireplace('python', 'py', $language);

    // Also, "normalize" C++ and C#.
    $language = str_ireplace('c++', 'cpp', $language);
    $language = str_ireplace('c#', 'cs', $language);

    // Ruby has "singleton method", for example.
    $type = substr(str_replace(' ', '_', $type), 0, 12);

    // class:foo, struct:foo, union:foo, enum:foo, ...
    $context = last(explode(':', $context, 2));

    $ignore = array(
      'variable' => true,
    );
    if (empty($ignore[$type])) {
      print_symbol($file_path, $line_num, $type, $token, $context, $language);
    }
  }
}

function ctags_get_parser_future($path) {
  $future = new ExecFuture('ctags -n --fields=Kls -o - %s', $path);
  return $future;
}

function ctags_check_executable() {
  $result = exec_manual('ctags --version');
  return !empty($result[1]);
}

function print_symbol($file, $line_num, $type, $token, $context, $language) {
  // Get rid of relative path.
  $file = explode('/', $file);
  if ($file[0] == '.' || $file[0] == '..') {
    array_shift($file);
  }
  $file = '/'.implode('/', $file);

  $parts = array(
    $context,
    $token,
    $type,
    strtolower($language),
    $line_num,
    $file,
  );
  echo implode(' ', $parts)."\n";
}
