<?php

final class PhabricatorClusterImproperWriteException
  extends PhabricatorClusterException {

  public function getExceptionTitle() {
    return pht('Improper Cluster Write');
  }

}
