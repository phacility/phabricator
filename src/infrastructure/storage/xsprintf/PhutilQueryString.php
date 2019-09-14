<?php

final class PhutilQueryString extends Phobject {

  private $maskedString;
  private $unmaskedString;

  public function __construct(PhutilQsprintfInterface $escaper, array $argv) {
    // Immediately render the query into a static scalar value.

    // This makes sure we throw immediately if there are errors in the
    // parameters, which is much better than throwing later on.

    // This also makes sure that later mutations to objects passed as
    // parameters won't affect the outcome. Consider:
    //
    //   $object->setTableName('X');
    //   $query = qsprintf($conn, '%R', $object);
    //   $object->setTableName('Y');
    //
    // We'd like "$query" to reference "X", reflecting the object as it
    // existed when it was passed to "qsprintf(...)". It's surprising if the
    // modification to the object after "qsprintf(...)" can affect "$query".

    $masked_string = xsprintf(
      'xsprintf_query',
      array(
        'escaper' => $escaper,
        'unmasked' => false,
      ),
      $argv);

    $unmasked_string = xsprintf(
      'xsprintf_query',
      array(
        'escaper' => $escaper,
        'unmasked' => true,
      ),
      $argv);

    $this->maskedString = $masked_string;
    $this->unmaskedString = $unmasked_string;
  }

  public function __toString() {
    return $this->getMaskedString();
  }

  public function getUnmaskedString() {
    return $this->unmaskedString;
  }

  public function getMaskedString() {
    return $this->maskedString;
  }

}
