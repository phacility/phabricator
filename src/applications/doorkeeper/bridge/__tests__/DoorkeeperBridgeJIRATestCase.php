<?php

final class DoorkeeperBridgeJIRATestCase extends PhabricatorTestCase {

  public function testJIRABridgeRestAPIURIConversion() {
    $map = array(
      array(
        // Installed at domain root.
        'http://jira.example.com/rest/api/2/issue/1',
        'TP-1',
        'http://jira.example.com/browse/TP-1',
      ),
      array(
        // Installed on path.
        'http://jira.example.com/jira/rest/api/2/issue/1',
        'TP-1',
        'http://jira.example.com/jira/browse/TP-1',
      ),
      array(
        // A URI we don't understand.
        'http://jira.example.com/wake/cli/3/task/1',
        'TP-1',
        null,
      ),
    );

    foreach ($map as $inputs) {
      list($rest_uri, $object_id, $expect) = $inputs;
      $this->assertEqual(
        $expect,
        DoorkeeperBridgeJIRA::getJIRAIssueBrowseURIFromJIRARestURI(
          $rest_uri,
          $object_id));
    }
  }

}
