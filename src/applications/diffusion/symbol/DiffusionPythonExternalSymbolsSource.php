<?php

final class DiffusionPythonExternalSymbolsSource
  extends DiffusionExternalSymbolsSource {

  public function executeQuery(DiffusionExternalSymbolQuery $query) {
    $symbols = array();
    if (!$query->matchesAnyLanguage(array('py', 'python'))) {
      return $symbols;
    }

    if (!$query->matchesAnyType(array('builtin', 'function'))) {
      return $symbols;
    }

    $names = $query->getNames();

    foreach ($names as $name) {
      if (idx(self::$python2Builtins, $name)) {
        $symbols[] = $this->buildExternalSymbol()
          ->setSymbolName($name)
          ->setSymbolType('function')
          ->setSource(pht('Standard Library'))
          ->setLocation(pht('The Python 2 Standard Library'))
          ->setSymbolLanguage('py')
          ->setExternalURI(
            'https://docs.python.org/2/library/functions.html#'.$name);
      }
      if (idx(self::$python3Builtins, $name)) {
        $symbols[] = $this->buildExternalSymbol()
          ->setSymbolName($name)
          ->setSymbolType('function')
          ->setSource(pht('Standard Library'))
          ->setLocation(pht('The Python 3 Standard Library'))
          ->setSymbolLanguage('py')
          ->setExternalURI(
            'https://docs.python.org/3/library/functions.html#'.$name);
      }
    }
    return $symbols;
  }

  private static $python2Builtins = array(
    '__import__' => true,
    'abs' => true,
    'all' => true,
    'any' => true,
    'basestring' => true,
    'bin' => true,
    'bool' => true,
    'bytearray' => true,
    'callable' => true,
    'chr' => true,
    'classmethod' => true,
    'cmp' => true,
    'compile' => true,
    'complex' => true,
    'delattr' => true,
    'dict' => true,
    'dir' => true,
    'divmod' => true,
    'enumerate' => true,
    'eval' => true,
    'execfile' => true,
    'file' => true,
    'filter' => true,
    'float' => true,
    'format' => true,
    'frozenset' => true,
    'getattr' => true,
    'globals' => true,
    'hasattr' => true,
    'hash' => true,
    'help' => true,
    'hex' => true,
    'id' => true,
    'input' => true,
    'int' => true,
    'isinstance' => true,
    'issubclass' => true,
    'iter' => true,
    'len' => true,
    'list' => true,
    'locals' => true,
    'long' => true,
    'map' => true,
    'max' => true,
    'memoryview' => true,
    'min' => true,
    'next' => true,
    'object' => true,
    'oct' => true,
    'open' => true,
    'ord' => true,
    'pow' => true,
    'print' => true,
    'property' => true,
    'range' => true,
    'raw_input' => true,
    'reduce' => true,
    'reload' => true,
    'repr' => true,
    'reversed' => true,
    'round' => true,
    'set' => true,
    'setattr' => true,
    'slice' => true,
    'sorted' => true,
    'staticmethod' => true,
    'str' => true,
    'sum' => true,
    'super' => true,
    'tuple' => true,
    'type' => true,
    'unichr' => true,
    'unicode' => true,
    'vars' => true,
    'xrange' => true,
    'zip' => true,
  );

  // This list only contains functions that are new or changed between the
  // Python versions.
  private static $python3Builtins = array(
    'ascii' => true,
    'bytes' => true,
    'filter' => true,
    'map' => true,
    'next' => true,
    'range' => true,
    'super' => true,
    'zip' => true,
  );
}
