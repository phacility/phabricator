<?php

final class PhabricatorApplicationConfigurationPanelTestCase
  extends PhabricatorTestCase {

  public function testLoadAllPanels() {
    PhabricatorApplicationConfigurationPanel::loadAllPanels();
    $this->assertTrue(true);
  }

}
