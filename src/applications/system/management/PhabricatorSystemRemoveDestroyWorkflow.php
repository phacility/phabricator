<?php

final class PhabricatorSystemRemoveDestroyWorkflow
  extends PhabricatorSystemRemoveWorkflow {

  protected function didConstruct() {
    $this
      ->setName('destroy')
      ->setSynopsis(pht('Permanently destroy objects.'))
      ->setExamples('**destroy** [__options__] __object__ ...')
      ->setArguments(
        array(
          array(
            'name' => 'force',
            'help' => pht('Destroy objects without prompting.'),
          ),
          array(
            'name' => 'objects',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $object_names = $args->getArg('objects');
    if (!$object_names) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more objects to destroy.'));
    }

    $object_query = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withNames($object_names);

    $object_query->execute();

    $named_objects = $object_query->getNamedResults();
    foreach ($object_names as $object_name) {
      if (empty($named_objects[$object_name])) {
        throw new PhutilArgumentUsageException(
          pht('No such object "%s" exists!', $object_name));
      }
    }

    foreach ($named_objects as $object_name => $object) {
      if (!($object instanceof PhabricatorDestructibleInterface)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Object "%s" can not be destroyed (it does not implement %s).',
            $object_name,
            'PhabricatorDestructibleInterface'));
      }
    }

    $console->writeOut(
      "<bg:red>**%s**</bg>\n\n",
      pht(' IMPORTANT: OBJECTS WILL BE PERMANENTLY DESTROYED! '));

    $console->writeOut(
      pht(
        "There is no way to undo this operation or ever retrieve this data.".
        "\n\n".
        "These %s object(s) will be **completely destroyed forever**:".
        "\n\n",
        new PhutilNumber(count($named_objects))));

    foreach ($named_objects as $object_name => $object) {
      $console->writeOut(
        "    - %s (%s)\n",
        $object_name,
        get_class($object));
    }

    $force = $args->getArg('force');
    if (!$force) {
      $ok = $console->confirm(
        pht(
          'Are you absolutely certain you want to destroy these %s object(s)?',
          new PhutilNumber(count($named_objects))));
      if (!$ok) {
        throw new PhutilArgumentUsageException(
          pht('Aborted, your objects are safe.'));
      }
    }

    $console->writeOut("%s\n", pht('Destroying objects...'));

    foreach ($named_objects as $object_name => $object) {
      $console->writeOut(
        pht(
          "Destroying %s **%s**...\n",
          get_class($object),
          $object_name));

      id(new PhabricatorDestructionEngine())
        ->destroyObject($object);
    }

    $console->writeOut(
      "%s\n",
      pht(
        'Permanently destroyed %s object(s).',
        new PhutilNumber(count($named_objects))));

    return 0;
  }

}
