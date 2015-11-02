<?php

abstract class PonderEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPonderApplication';
  }

   protected function getMailSubjectPrefix() {
    return '[Ponder]';
  }

}
