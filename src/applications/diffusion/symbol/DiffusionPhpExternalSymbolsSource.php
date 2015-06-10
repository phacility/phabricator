<?php

final class DiffusionPhpExternalSymbolsSource
  extends DiffusionExternalSymbolsSource {

  public function executeQuery(DiffusionExternalSymbolQuery $query) {
    $symbols = array();

    if (!$query->matchesAnyLanguage(array('php'))) {
      return $symbols;
    }

    $names = $query->getNames();

    if ($query->matchesAnyType(array('function'))) {
      $functions = get_defined_functions();
      $functions = $functions['internal'];

      foreach ($names as $name) {
        if (in_array($name, $functions)) {
          $symbols[] = $this->buildExternalSymbol()
            ->setSymbolName($name)
            ->setSymbolType('function')
            ->setSource(pht('PHP'))
            ->setLocation(pht('Manual at php.net'))
            ->setSymbolLanguage('php')
            ->setExternalURI('http://www.php.net/function.'.$name);
        }
      }
    }
    if ($query->matchesAnyType(array('class'))) {
      foreach ($names as $name) {
        if (class_exists($name, false) || interface_exists($name, false)) {
          if (id(new ReflectionClass($name))->isInternal()) {
            $symbols[] = $this->buildExternalSymbol()
              ->setSymbolName($name)
              ->setSymbolType('class')
              ->setSource(pht('PHP'))
              ->setLocation(pht('Manual at php.net'))
              ->setSymbolLanguage('php')
              ->setExternalURI('http://www.php.net/class.'.$name);
          }
        }
      }
    }

    return $symbols;
  }
}
