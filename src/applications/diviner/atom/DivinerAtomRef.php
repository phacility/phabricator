<?php

final class DivinerAtomRef extends Phobject {

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
    if (preg_match('/^@\d+\z/', $normal_name)) {
      throw new Exception(
        pht(
          "Atom names must not be in the form '%s'. This pattern is ".
          "reserved for disambiguating atoms with similar names.",
          '/@\d+/'));
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

  public function getTitleSlug() {
    return self::normalizeTitleString($this->getTitle());
  }

  public function toDictionary() {
    return array(
      'book'    => $this->getBook(),
      'context' => $this->getContext(),
      'type'    => $this->getType(),
      'name'    => $this->getName(),
      'group'   => $this->getGroup(),
      'summary' => $this->getSummary(),
      'index'   => $this->getIndex(),
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
    return id(new DivinerAtomRef())
      ->setBook(idx($dict, 'book'))
      ->setContext(idx($dict, 'context'))
      ->setType(idx($dict, 'type'))
      ->setName(idx($dict, 'name'))
      ->setGroup(idx($dict, 'group'))
      ->setSummary(idx($dict, 'summary'))
      ->setIndex(idx($dict, 'index'))
      ->setTitle(idx($dict, 'title'));
  }

  public static function normalizeString($str) {
    // These characters create problems on the filesystem or in URIs. Replace
    // them with non-problematic approximations (instead of simply removing
    // them) to keep the URIs fairly useful and avoid unnecessary collisions.
    // These approximations are selected based on some domain knowledge of
    // common languages: where a character is used as a delimiter, it is more
    // helpful to replace it with a "." or a ":" or similar, while it's better
    // if operator overloads read as, e.g., "operator_div".

    $map = array(
      // Hopefully not used anywhere by anything.
      '#' => '.',

      // Used in Ruby methods.
      '?' => 'Q',

      // Used in PHP namespaces.
      '\\' => '.',

      // Used in "operator +" in C++.
      '+' => 'plus',

      // Used in "operator %" in C++.
      '%' => 'mod',

      // Used in "operator /" in C++.
      '/' => 'div',
    );
    $str = str_replace(array_keys($map), array_values($map), $str);

    // Replace all spaces with underscores.
    $str = preg_replace('/ +/', '_', $str);

    // Replace control characters with "X".
    $str = preg_replace('/[\x00-\x19]/', 'X', $str);

    // Replace specific problematic names with alternative names.
    $alternates = array(
      '.'  => 'dot',
      '..' => 'dotdot',
      ''   => 'null',
    );

    return idx($alternates, $str, $str);
  }

  public static function normalizeTitleString($str) {
    // Remove colons from titles. This is mostly to accommodate legacy rules
    // from the old Diviner, which generated a significant number of article
    // URIs without colons present in the titles.
    $str = str_replace(':', '', $str);
    $str = self::normalizeString($str);
    return phutil_utf8_strtolower($str);
  }

}
