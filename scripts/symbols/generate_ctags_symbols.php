#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

if (ctags_check_executable() == false) {
  echo phutil_console_format(
    "Could not find Exuberant ctags. Make sure it is installed and\n".
    "available in executable path.\n\n".
    "Exuberant ctags project page: http://ctags.sourceforge.net/\n");
  exit(1);
}

if ($argc !== 1 || posix_isatty(STDIN)) {
  echo phutil_console_format(
    "usage: find . -type f -name '*.py' | ./generate_ctags_symbols.php\n");
  exit(1);
}

$input = file_get_contents('php://stdin');
$input = trim($input);
$input = explode("\n", $input);

$data = array();
$futures = array();

foreach ($input as $file) {
  $file = Filesystem::readablePath($file);
  $futures[$file] = ctags_get_parser_future($file);
}

foreach (Futures($futures)->limit(8) as $file => $future) {
  $tags = $future->resolve();
  $tags = explode("\n", $tags[1]);

  foreach ($tags as $tag) {
    $parts = explode(";", $tag);
    // skip lines that we can not parse
    if (count($parts) < 2) {
      continue;
    }

    // split ctags information
    $tag_info = explode("\t", $parts[0]);
    // split exuberant ctags "extension fields" (additional information)
    $parts[1] = trim($parts[1], "\t \"");
    $extension_fields = explode("\t", $parts[1]);

    // skip lines that we can not parse
    if (count($tag_info) < 3 || count($extension_fields) < 2) {
      continue;
    }

    // default $context to empty
    $extension_fields[] = '';
    list($token, $file_path, $line_num) = $tag_info;
    list($type, $language, $context) = $extension_fields;

    // skip lines with tokens containing a space
    if (strpos($token, ' ') !== false) {
      continue;
    }

    // strip "language:"
    $language = substr($language, 9);

    // To keep consistent with "Separate with commas, for example: php, py"
    // in Arcanist Project edit form.
    $language = str_ireplace("python", "py", $language);

    // also, "normalize" c++ and c#
    $language = str_ireplace("c++", "cpp", $language);
    $language = str_ireplace("c#", "csharp", $language);

    // Ruby has "singleton method", for example
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

function ctags_get_parser_future($file_path) {
  $future = new ExecFuture('ctags -n --fields=Kls -o - %s',
                           $file_path);
  return $future;
}

function ctags_check_executable() {
  $future = new ExecFuture('ctags --version');
  $result = $future->resolve();

  if (empty($result[1])) {
    return false;
  }

  return true;
}

function print_symbol($file, $line_num, $type, $token, $context, $language) {
  // get rid of relative path
  $file = explode('/', $file);
  if ($file[0] == '.' || $file[0] == "..") {
    array_shift($file);
  }
  $file = '/' . implode('/', $file);

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
