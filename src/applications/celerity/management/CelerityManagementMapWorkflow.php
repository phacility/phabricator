<?php

final class CelerityManagementMapWorkflow
  extends CelerityManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('map')
      ->setExamples('**map** [options]')
      ->setSynopsis(pht('Rebuild static resource maps.'))
      ->setArguments(
        array());
  }

  public function execute(PhutilArgumentParser $args) {
    $resources_map = CelerityPhysicalResources::getAll();

    $this->log(
      pht(
        'Rebuilding %d resource source(s).',
        new PhutilNumber(count($resources_map))));

    foreach ($resources_map as $name => $resources) {
      $this->rebuildResources($resources);
    }

    $this->log(pht('Done.'));

    return 0;
  }

  /**
   * Rebuild the resource map for a resource source.
   *
   * @param CelerityPhysicalResources Resource source to rebuild.
   * @return void
   */
  private function rebuildResources(CelerityPhysicalResources $resources) {
    $this->log(
      pht(
        'Rebuilding resource source "%s" (%s)...',
        $resources->getName(),
        get_class($resources)));

    id(new CelerityResourceMapGenerator($resources))
      ->setDebug(true)
      ->generate()
      ->write();
  }

  protected function log($message) {
    $console = PhutilConsole::getConsole();
    $console->writeErr("%s\n", $message);
  }

}
