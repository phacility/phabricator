<?php

final class PhabricatorCelerityTestCase extends PhabricatorTestCase {

  /**
   * This is more of an acceptance test case instead of a unit test. It verifies
   * that the Celerity map is up-to-date.
   */
  public function testCelerityMaps() {
    $resources_map = CelerityPhysicalResources::getAll();

    foreach ($resources_map as $resources) {
      $old_map = new CelerityResourceMap($resources);

      $new_map = id(new CelerityResourceMapGenerator($resources))
        ->generate();

      $this->assertEqual(
        $new_map->getNameMap(),
        $old_map->getNameMap());
      $this->assertEqual(
        $new_map->getSymbolMap(),
        $old_map->getSymbolMap());
      $this->assertEqual(
        $new_map->getRequiresMap(),
        $old_map->getRequiresMap());
      $this->assertEqual(
        $new_map->getPackageMap(),
        $old_map->getPackageMap());
    }
  }

}
