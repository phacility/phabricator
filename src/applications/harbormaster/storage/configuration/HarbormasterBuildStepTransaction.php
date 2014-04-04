<?php

final class HarbormasterBuildStepTransaction
  extends PhabricatorApplicationTransaction {

  public function getApplicationName() {
    return 'harbormaster';
  }

  public function getApplicationTransactionType() {
    return HarbormasterPHIDTypeBuildStep::TYPECONST;
  }

}
