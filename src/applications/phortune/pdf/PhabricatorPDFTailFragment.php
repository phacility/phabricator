<?php

final class PhabricatorPDFTailFragment
  extends PhabricatorPDFFragment {

  private $iterator;

  public function setIterator(PhabricatorPDFIterator $iterator) {
    $this->iterator = $iterator;
    return $this;
  }

  public function getIterator() {
    return $this->iterator;
  }

  protected function writeFragment() {
    $iterator = $this->getIterator();
    $generator = $iterator->getGenerator();
    $objects = $generator->getObjects();

    $xref_offset = null;

    $this->writeLine('xref');
    $this->writeLine('0 %d', count($objects) + 1);
    $this->writeLine('%010d %05d f ', 0, 0xFFFF);

    $offset_map = array();

    $fragment_offsets = $iterator->getFragmentOffsets();
    foreach ($fragment_offsets as $fragment_offset) {
      $fragment = $fragment_offset->getFragment();
      $offset = $fragment_offset->getOffset();

      if ($fragment === $this) {
        $xref_offset = $offset;
      }

      if (!$fragment->hasRefTableEntry()) {
        continue;
      }

      $offset_map[$fragment->getObjectIndex()] = $offset;
    }

    ksort($offset_map);

    foreach ($offset_map as $offset) {
      $this->writeLine('%010d %05d n ', $offset, 0);
    }

    $this->writeLine('trailer');
    $this->writeLine('<<');
    $this->writeLine('/Size %d', count($objects) + 1);

    $info_object = $generator->getInfoObject();
    if ($info_object) {
     $this->writeLine('/Info %d 0 R', $info_object->getObjectIndex());
    }

    $catalog_object = $generator->getCatalogObject();
    if ($catalog_object) {
      $this->writeLine('/Root %d 0 R', $catalog_object->getObjectIndex());
    }

    $this->writeLine('>>');
    $this->writeLine('startxref');
    $this->writeLine('%d', $xref_offset);
    $this->writeLine('%s', '%%EOF');
  }

}
