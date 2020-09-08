<?php

final class HarbormasterManagementPublishWorkflow
  extends HarbormasterManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('publish')
      ->setExamples(pht('**publish** __buildable__ ...'))
      ->setSynopsis(
        pht(
          'Publish a buildable. This is primarily useful for developing '.
          'and debugging applications which have buildable objects.'))
      ->setArguments(
        array(
          array(
            'name' =>  'buildable',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $buildable_names = $args->getArg('buildable');
    if (!$buildable_names) {
      throw new PhutilArgumentUsageException(
        pht(
          'Name one or more buildables to publish, like "B123".'));
    }

    $query = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames($buildable_names);

    $query->execute();

    $result_map = $query->getNamedResults();

    foreach ($buildable_names as $name) {
      if (!isset($result_map[$name])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Argument "%s" does not name a buildable. Provide one or more '.
            'valid buildable monograms or PHIDs.',
            $name));
      }
    }

    foreach ($result_map as $name => $result) {
      if (!($result instanceof HarbormasterBuildable)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Object "%s" is not a HarbormasterBuildable (it is a "%s"). '.
            'Name one or more buildables to publish, like "B123".',
            $name,
            get_class($result)));
      }
    }

    foreach ($result_map as $buildable) {
      echo tsprintf(
        "%s\n",
        pht(
          'Publishing "%s"...',
        $buildable->getMonogram()));

      // Reload the buildable to pick up builds.
      $buildable = id(new HarbormasterBuildableQuery())
        ->setViewer($viewer)
        ->withIDs(array($buildable->getID()))
        ->needBuilds(true)
        ->executeOne();

      $engine = id(new HarbormasterBuildEngine())
        ->setViewer($viewer)
        ->publishBuildable($buildable, $buildable);
    }

    echo tsprintf(
      "%s\n",
      pht('Done.'));

    return 0;
  }

}
