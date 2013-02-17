<?php

final class DivinerAtomRef {

  private $book;
  private $context;
  private $type;
  private $name;

  public function setName($name) {
    $normal_name = self::normalizeString($name);
    if (preg_match('/^@[0-9]+$/', $normal_name)) {
      throw new Exception(
        "Atom names must not be in the form '/@\d+/'. This pattern is ".
        "reserved for disambiguating atoms with similar names.");
    }
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setType($type) {
    $this->type = self::normalizeString($type);
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setContext($context) {
    if ($context === null) {
      $this->context = $context;
    } else {
      $this->context = self::normalizeString($context);
    }
    return $this;
  }

  public function getContext() {
    return $this->context;
  }

  public function setBook($book) {
    $this->book = self::normalizeString($book);
    return $this;
  }

  public function getBook() {
    return $this->book;
  }

  public function toDictionary() {
    return array(
      'book'    => $this->getBook(),
      'context' => $this->getContext(),
      'type'    => $this->getType(),
      'name'    => $this->getName(),
    );
  }

  public function toHash() {
    $dict = $this->toDictionary();
    ksort($dict);
    return md5(serialize($dict)).'S';
  }

  public static function newFromDictionary(array $dict) {
    $obj = new DivinerAtomRef();
    $obj->book = idx($dict, 'book');
    $obj->context = idx($dict, 'context');
    $obj->type = idx($dict, 'type');
    $obj->name = idx($dict, 'name');
    return $obj;
  }

  public static function normalizeString($str) {
    // These characters create problems on the filesystem or in URIs. Replace
    // them with non-problematic appoximations (instead of simply removing them)
    // to keep the URIs fairly useful and avoid unnecessary collisions. These
    // approximations are selected based on some domain knowledge of common
    // languages: where a character is used as a delimiter, it is more helpful
    // to replace it with a "." or a ":" or similar, while it's better if
    // operator overloads read as, e.g., "operator_div".

    $map = array(
      // Hopefully not used anywhere by anything.
      '#'   => '.',

      // Used in Ruby methods.
      '?'   => 'Q',

      // Used in PHP namespaces.
      '\\'  => '.',

      // Used in "operator +" in C++.
      '+'   => 'plus',

      // Used in "operator %" in C++.
      '%'   => 'mod',

      // Used in "operator /" in C++.
      '/'   => 'div',
    );
    $str = str_replace(array_keys($map), array_values($map), $str);

    // Replace all spaces with underscores.
    $str = preg_replace('/ +/', '_', $str);

    // Replace control characters with "@".
    $str = preg_replace('/[\x00-\x19]/', '@', $str);

    // Replace specific problematic names with alternative names.
    $alternates = array(
      '.'   => 'dot',
      '..'  => 'dotdot',
      ''    => 'null',
    );

    return idx($alternates, $str, $str);
  }

}
