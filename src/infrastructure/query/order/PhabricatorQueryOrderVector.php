<?php

/**
 * Structural class representing a column ordering for a query.
 *
 * Queries often order results on multiple columns. For example, projects might
 * be ordered by "name, id". This class wraps a list of column orderings and
 * makes them easier to manage.
 *
 * To construct an order vector, use @{method:newFromVector}:
 *
 *   $vector = PhabricatorQueryOrderVector::newFromVector(array('name', 'id'));
 *
 * You can iterate over an order vector normally:
 *
 *   foreach ($vector as $item) {
 *     // ...
 *   }
 *
 * The items are objects of class @{class:PhabricatorQueryOrderItem}.
 *
 * This class is primarily internal to the query infrastructure, and most
 * application code should not need to interact with it directly.
 */
final class PhabricatorQueryOrderVector
  extends Phobject
  implements Iterator {

  private $items;
  private $keys;
  private $cursor;

  private function __construct() {
    // <private>
  }

  public static function newFromVector($vector) {
    if ($vector instanceof PhabricatorQueryOrderVector) {
      return (clone $vector);
    }

    if (!is_array($vector)) {
      throw new Exception(
        pht(
          'An order vector can only be constructed from a list of strings or '.
          'another order vector.'));
    }

    if (!$vector) {
      throw new Exception(
        pht(
          'An order vector must not be empty.'));
    }

    $items = array();
    foreach ($vector as $key => $scalar) {
      if (!is_string($scalar)) {
        throw new Exception(
          pht(
            'Value with key "%s" in order vector is not a string (it has '.
            'type "%s"). An order vector must contain only strings.',
            $key,
            gettype($scalar)));
      }

      $item = PhabricatorQueryOrderItem::newFromScalar($scalar);

      // Orderings like "id, id, id" or "id, -id" are meaningless and invalid.
      if (isset($items[$item->getOrderKey()])) {
        throw new Exception(
          pht(
            'Order vector "%s" specifies order "%s" twice. Each component '.
            'of an ordering must be unique.',
            implode(', ', $vector),
            $item->getOrderKey()));
      }

      $items[$item->getOrderKey()] = $item;
    }

    $obj = new PhabricatorQueryOrderVector();
    $obj->items = $items;
    $obj->keys = array_keys($items);
    return $obj;
  }

  public function appendVector($vector) {
    $vector = self::newFromVector($vector);

    // When combining vectors (like "group by" and "order by" vectors), there
    // may be redundant columns. We only want to append unique columns which
    // aren't already present in the vector.
    foreach ($vector->items as $key => $item) {
      if (empty($this->items[$key])) {
        $this->items[$key] = $item;
        $this->keys[] = $key;
      }
    }

    return $this;
  }

  public function getAsString() {
    $scalars = array();
    foreach ($this->items as $item) {
      $scalars[] = $item->getAsScalar();
    }
    return implode(', ', $scalars);
  }

  public function containsKey($key) {
    return isset($this->items[$key]);
  }


/* -(  Iterator Interface  )------------------------------------------------- */


  public function rewind() {
    $this->cursor = 0;
  }


  public function current() {
    return $this->items[$this->key()];
  }


  public function key() {
    return $this->keys[$this->cursor];
  }


  public function next() {
    ++$this->cursor;
  }


  public function valid() {
    return isset($this->keys[$this->cursor]);
  }

}
