<?php

final class PhabricatorClusterStrandedException
  extends PhabricatorClusterException {

  public function getExceptionTitle() {
    return pht('Unable to Reach Any Database');
  }

}
