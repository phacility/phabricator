<?php

final class PhabricatorTokenReceiverQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $tokenCounts;

  protected function loadPage() {
    $table = new PhabricatorTokenCount();
    $conn_r = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn_r,
      'SELECT objectPHID, tokenCount FROM %T ORDER BY tokenCount DESC',
      $table->getTableName());

    $this->tokenCounts = ipull($rows, 'tokenCount', 'objectPHID');
    return ipull($rows, 'objectPHID');
  }

  protected function willFilterPage(array $phids) {
    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($phids)
      ->execute();

    // Reorder the objects in the input order.
    $objects = array_select_keys($objects, $phids);

    return $objects;
  }

  public function getTokenCounts() {
    return $this->tokenCounts;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorTokensApplication';
  }

}
