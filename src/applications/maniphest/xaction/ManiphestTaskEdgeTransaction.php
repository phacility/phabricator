<?php

final class ManiphestTaskEdgeTransaction
  extends ManiphestTaskTransactionType {

  const TRANSACTIONTYPE = 'edge';

  public function generateOldValue($object) {
    return null;
  }

  public function shouldHide() {
    $commit_phid = $this->getMetadataValue('commitPHID');
    $edge_type = $this->getMetadataValue('edge:type');

    if ($edge_type == ManiphestTaskHasCommitEdgeType::EDGECONST) {
      if ($commit_phid) {
        return true;
      }
    }
  }

  public function getActionName() {
    return pht('Attached');
  }

  public function getIcon() {
    return 'fa-thumb-tack';
  }

}
