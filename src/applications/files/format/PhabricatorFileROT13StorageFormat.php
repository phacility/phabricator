<?php

/**
 * Trivial example of a file storage format for at-rest encryption.
 *
 * This format applies ROT13 encoding to file data as it is stored and
 * reverses it on the way out. This encoding is trivially reversible. This
 * format is for testing, developing, and understanding encoding formats and
 * is not intended for production use.
 */
final class PhabricatorFileROT13StorageFormat
  extends PhabricatorFileStorageFormat {

  const FORMATKEY = 'rot13';

  public function getStorageFormatName() {
    return pht('Encoded (ROT13)');
  }

  public function newReadIterator($raw_iterator) {
    $file = $this->getFile();
    $iterations = $file->getStorageProperty('iterations', 1);

    $value = $file->loadDataFromIterator($raw_iterator);
    for ($ii = 0; $ii < $iterations; $ii++) {
      $value = str_rot13($value);
    }

    return array($value);
  }

  public function newWriteIterator($raw_iterator) {
    return $this->newReadIterator($raw_iterator);
  }

  public function newStorageProperties() {
    // For extreme security, repeatedly encode the data using a random (odd)
    // number of iterations.
    return array(
      'iterations' => (mt_rand(1, 3) * 2) - 1,
    );
  }

}
