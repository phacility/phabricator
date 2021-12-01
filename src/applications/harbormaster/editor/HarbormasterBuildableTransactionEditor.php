<?php

final class HarbormasterBuildableTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Harbormaster Buildables');
  }

}
