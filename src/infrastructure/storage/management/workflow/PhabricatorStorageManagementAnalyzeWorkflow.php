<?php

final class PhabricatorStorageManagementAnalyzeWorkflow
  extends PhabricatorStorageManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('analyze')
      ->setExamples('**analyze**')
      ->setSynopsis(
        pht('Run "ANALYZE TABLE" on tables to improve performance.'));
  }

  public function didExecute(PhutilArgumentParser $args) {
    $api = $this->getSingleAPI();
    $this->analyzeTables($api);
    return 0;
  }

}
