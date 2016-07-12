<?php

final class DivinerAtomizeWorkflow extends DivinerWorkflow {

  protected function didConstruct() {
    $this
      ->setName('atomize')
      ->setSynopsis(pht('Build atoms from source.'))
      ->setArguments(
        array(
          array(
            'name' => 'atomizer',
            'param' => 'class',
            'help' => pht('Specify a subclass of %s.', 'DivinerAtomizer'),
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
    $this->readBookConfiguration($args->getArg('book'));

    $console = PhutilConsole::getConsole();

    $atomizer_class = $args->getArg('atomizer');
    if (!$atomizer_class) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify an atomizer class with %s.',
          '--atomizer'));
    }

    $symbols = id(new PhutilSymbolLoader())
      ->setName($atomizer_class)
      ->setConcreteOnly(true)
      ->setAncestorClass('DivinerAtomizer')
      ->selectAndLoadSymbols();
    if (!$symbols) {
      throw new PhutilArgumentUsageException(
        pht(
          "Atomizer class '%s' must be a concrete subclass of %s.",
          $atomizer_class,
          'DivinerAtomizer'));
    }

    $atomizer = newv($atomizer_class, array());

    $files = $args->getArg('files');
    if (!$files) {
      throw new Exception(pht('Specify one or more files to atomize.'));
    }

    $file_atomizer = new DivinerFileAtomizer();

    foreach (array($atomizer, $file_atomizer) as $configure) {
      $configure->setBook($this->getConfig('name'));
    }

    $group_rules = array();
    foreach ($this->getConfig('groups', array()) as $group => $spec) {
      $include = (array)idx($spec, 'include', array());
      foreach ($include as $pattern) {
        $group_rules[$pattern] = $group;
      }
    }

    $all_atoms = array();
    $context = array(
      'group' => null,
    );
    foreach ($files as $file) {
      $abs_path = Filesystem::resolvePath($file, $this->getConfig('root'));
      $data = Filesystem::readFile($abs_path);

      if (!$this->shouldAtomizeFile($file, $data)) {
        $console->writeLog("%s\n", pht('Skipping %s...', $file));
        continue;
      } else {
        $console->writeLog("%s\n", pht('Atomizing %s...', $file));
      }

      $context['group'] = null;
      foreach ($group_rules as $rule => $group) {
        if (preg_match($rule, $file)) {
          $context['group'] = $group;
          break;
        }
      }

      $file_atoms = $file_atomizer->atomize($file, $data, $context);
      $all_atoms[] = $file_atoms;

      if (count($file_atoms) !== 1) {
        throw new Exception(
          pht('Expected exactly one atom from file atomizer.'));
      }
      $file_atom = head($file_atoms);

      $atoms = $atomizer->atomize($file, $data, $context);

      foreach ($atoms as $atom) {
        if (!$atom->hasParent()) {
          $file_atom->addChild($atom);
        }
      }

      $all_atoms[] = $atoms;
    }

    $all_atoms = array_mergev($all_atoms);

    $all_atoms = mpull($all_atoms, 'toDictionary');
    $all_atoms = ipull($all_atoms, null, 'hash');

    if ($args->getArg('ugly')) {
      $json = json_encode($all_atoms);
    } else {
      $json = id(new PhutilJSON())->encodeFormatted($all_atoms);
    }

    $console->writeOut('%s', $json);
    return 0;
  }

  private function shouldAtomizeFile($file_name, $file_data) {
    return strpos($file_data, '@'.'undivinable') === false;
  }

}
