<?php

final class DivinerAtomizeWorkflow extends DivinerWorkflow {

  public function didConstruct() {
    $this
      ->setName('atomize')
      ->setSynopsis(pht('Build atoms from source.'))
      ->setArguments(
        array(
          array(
            'name' => 'atomizer',
            'param' => 'class',
            'help' => pht('Specify a subclass of DivinerAtomizer.'),
          ),
          array(
            'name' => 'book',
            'param' => 'path',
            'help' => pht('Path to a Diviner book configuration.'),
          ),
          array(
            'name' => 'files',
            'wildcard' => true,
          ),
          array(
            'name' => 'ugly',
            'help' => pht('Produce ugly (but faster) output.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $this->readBookConfiguration($args);

    $console = PhutilConsole::getConsole();

    $atomizer_class = $args->getArg('atomizer');
    if (!$atomizer_class) {
      throw new Exception("Specify an atomizer class with --atomizer.");
    }

    $symbols = id(new PhutilSymbolLoader())
      ->setName($atomizer_class)
      ->setConcreteOnly(true)
      ->setAncestorClass('DivinerAtomizer')
      ->selectAndLoadSymbols();
    if (!$symbols) {
      throw new Exception(
        "Atomizer class '{$atomizer_class}' must be a concrete subclass of ".
        "DivinerAtomizer.");
    }

    $atomizer = newv($atomizer_class, array());

    $files = $args->getArg('files');
    if (!$files) {
      throw new Exception("Specify one or more files to atomize.");
    }

    $file_atomizer = new DivinerFileAtomizer();

    foreach (array($atomizer, $file_atomizer) as $configure) {
      $configure->setBook($this->getConfig('name'));
    }

    $all_atoms = array();
    foreach ($files as $file) {
      $abs_path = Filesystem::resolvePath($file, $this->getConfig('root'));
      $data = Filesystem::readFile($abs_path);

      if (!$this->shouldAtomizeFile($file, $data)) {
        $console->writeLog("Skipping %s...\n", $file);
        continue;
      } else {
        $console->writeLog("Atomizing %s...\n", $file);
      }

      $file_atoms = $file_atomizer->atomize($file, $data);
      $all_atoms[] = $file_atoms;

      if (count($file_atoms) !== 1) {
        throw new Exception("Expected exactly one atom from file atomizer.");
      }
      $file_atom = head($file_atoms);

      $atoms = $atomizer->atomize($file, $data);

      foreach ($atoms as $atom) {
        $file_atom->addChild($atom);
      }

      $all_atoms[] = $atoms;
    }

    $all_atoms = array_mergev($all_atoms);

    $all_atoms = mpull($all_atoms, 'toDictionary');
    $all_atoms = ipull($all_atoms, null, 'hash');

    if ($args->getArg('ugly')) {
      $json = json_encode($all_atoms);
    } else {
      $json_encoder = new PhutilJSON();
      $json = $json_encoder->encodeFormatted($all_atoms);
    }

    $console->writeOut('%s', $json);

    return 0;
  }

  private function shouldAtomizeFile($file_name, $file_data) {
    return (strpos($file_data, '@'.'undivinable') === false);
  }

}
