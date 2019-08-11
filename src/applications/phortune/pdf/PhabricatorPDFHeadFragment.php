<?php

final class PhabricatorPDFHeadFragment
  extends PhabricatorPDFFragment {

  protected function writeFragment() {
    $this->writeLine('%s', '%PDF-1.3');
  }

}
