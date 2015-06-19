<?php

final class MetaMTAEmailTransactionCommandTestCase extends PhabricatorTestCase {

  public function testGetAllTypes() {
    MetaMTAEmailTransactionCommand::getAllCommands();
    $this->assertTrue(true);
  }

}
