<?php

final class PhabricatorClusterImpossibleWriteException
  extends PhabricatorClusterException {

  public function getExceptionTitle() {
    return pht('Impossible Cluster Write');
  }

}
