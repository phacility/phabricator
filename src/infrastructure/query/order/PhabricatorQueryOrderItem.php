<?php

/**
 * Structural class representing one item in an order vector.
 *
 * See @{class:PhabricatorQueryOrderVector} for discussion of order vectors.
 * This represents one item in an order vector, like "id". When combined with
 * the other items in the vector, a complete ordering (like "name, id") is
 * described.
 *
 * Construct an item using @{method:newFromScalar}:
 *
 *   $item = PhabricatorQueryOrderItem::newFromScalar('id');
 *
 * This class is primarily internal to the query infrastructure, and most
 * application code should not need to interact with it directly.
 */
final class PhabricatorQueryOrderItem
  extends Phobject {

  private $orderKey;
  private $isReversed;

  private function __construct() {
    // <private>
  }

  public static function newFromScalar($scalar) {
    // If the string is something like "-id", strip the "-" off and mark it
    // as reversed.
    $is_reversed = false;
    if (!strncmp($scalar, '-', 1)) {
      $is_reversed = true;
      $scalar = substr($scalar, 1);
    }

    $item = new PhabricatorQueryOrderItem();
    $item->orderKey = $scalar;
    $item->isReversed = $is_reversed;

    return $item;
  }

  public function getIsReversed() {
    return $this->isReversed;
  }

  public function getOrderKey() {
    return $this->orderKey;
  }

  public function getAsScalar() {
    if ($this->getIsReversed()) {
      $prefix = '-';
    } else {
      $prefix = '';
    }

    return $prefix.$this->getOrderKey();
  }

}
