<?php

final class DivinerAtomRef {

  private $book;
  private $context;
  private $type;
  private $name;
  private $group;
  private $summary;
  private $index;
  private $title;

  public function getSortKey() {
    return implode(
      "\0",
      array(
        $this->getName(),
        $this->getType(),
        $this->getContext(),
        $this->getBook(),
        $this->getIndex(),
      ));
  }

  public function setIndex($index) {
    $this->index = $index;
    return $this;
  }

  public function getIndex() {
    return $this->index;
  }

  public function setSummary($summary) {
    $this->summary = $summary;
    return $this;
  }

  public function getSummary() {
    return $this->summary;
  }

  public function setName($name) {
    $normal_name = self::normalizeString($name);
    if (preg_match('/^@[0-9]+$/', $normal_name)) {
      throw new Exception(
        "Atom names must not be in the form '/@\d+/'. This pattern is ".
        "reserved for disambiguating atoms with similar names.");
    }
    $this->name = $normal_name;
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
    if ($book === null) {
      $this->book = $book;
    } else {
      $this->book = self::normalizeString($book);
    }
    return $this;
  }

  public function getBook() {
    return $this->book;
  }

  public function setGroup($group) {
    $this->group = $group;
    return $this;
  }

  public function getGroup() {
    return $this->group;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  public function toDictionary() {
    return array(
      'book'    => $this->getBook(),
      'context' => $this->getContext(),
      'type'    => $this->getType(),
      'name'    => $this->getName(),
      'group'   => $this->getGroup(),
      'index'   => $this->getIndex(),
      'summary' => $this->getSummary(),
      'title'   => $this->getTitle(),
    );
  }

  public function toHash() {
    $dict = $this->toDictionary();

    unset($dict['group']);
    unset($dict['index']);
    unset($dict['summary']);
    unset($dict['title']);

    ksort($dict);
    return md5(serialize($dict)).'S';
  }

  public static function newFromDictionary(array $dict) {
    $obj = new DivinerAtomRef();
    $obj->setBook(idx($dict, 'book'));
    $obj->setContext(idx($dict, 'context'));
    $obj->setType(idx($dict, 'type'));
    $obj->setName(idx($dict, 'name'));
    $obj->group = idx($dict, 'group');
    $obj->index = idx($dict, 'index');
    $obj->summary = idx($dict, 'summary');
    $obj->title = idx($dict, 'title');
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

    // Replace control characters with "X".
    $str = preg_replace('/[\x00-\x19]/', 'X', $str);

    // Replace specific problematic names with alternative names.
    $alternates = array(
      '.'   => 'dot',
      '..'  => 'dotdot',
      ''    => 'null',
    );

    return idx($alternates, $str, $str);
  }

}
