<?php

final class PhabricatorPDFFontObject
  extends PhabricatorPDFObject {

  protected function writeObject() {
    $this->writeLine('/Type /Font');

    $this->writeLine('/BaseFont /Helvetica-Bold');
    $this->writeLine('/Subtype /Type1');
    $this->writeLine('/Encoding /WinAnsiEncoding');
  }

}
