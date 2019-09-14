<?php

final class PhabricatorPDFResourcesObject
  extends PhabricatorPDFObject {

  private $fontObjects = array();

  public function addFontObject(PhabricatorPDFFontObject $font) {
    $this->fontObjects[] = $this->newChildObject($font);
    return $this;
  }

  public function getFontObjects() {
    return $this->fontObjects;
  }

  protected function writeObject() {
    $this->writeLine('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');

    $fonts = $this->getFontObjects();
    foreach ($fonts as $font) {
      $this->writeLine('/Font <<');
      $this->writeLine('/F%d %d 0 R', 1, $font->getObjectIndex());
      $this->writeLine('>>');
    }
  }

}
