<?php

final class PhabricatorFileRawStorageFormat
  extends PhabricatorFileStorageFormat {

  const FORMATKEY = 'raw';

  public function getStorageFormatName() {
    return pht('Raw Data');
  }

  public function newReadIterator($raw_iterator) {
    return $raw_iterator;
  }

  public function newWriteIterator($raw_iterator) {
    return $raw_iterator;
  }

}
