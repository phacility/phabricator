<?php

final class DivinerAtom {

  const TYPE_FILE      = 'file';
  const TYPE_ARTICLE   = 'article';

  private $type;
  private $name;
  private $file;
  private $line;
  private $hash;
  private $contentRaw;
  private $length;
  private $language;
  private $docblockRaw;
  private $docblockText;
  private $docblockMeta;
  private $warnings = array();
  private $parentHash;
  private $childHashes = array();
  private $context;
  private $extends = array();
  private $links = array();
  private $book;

  /**
   * Returns a sorting key which imposes an unambiguous, stable order on atoms.
   */
  public function getSortKey() {
    return implode(
      "\0",
      array(
        $this->getBook(),
        $this->getType(),
        $this->getContext(),
        $this->getName(),
        $this->getFile(),
        sprintf('%08', $this->getLine()),
      ));
  }

  public function setBook($book) {
    $this->book = $book;
    return $this;
  }

  public function getBook() {
    return $this->book;
  }

  public function setContext($context) {
    $this->context = $context;
    return $this;
  }

  public function getContext() {
    return $this->context;
  }

  public static function getAtomSerializationVersion() {
    return 1;
  }

  public function addWarning($warning) {
    $this->warnings[] = $warning;
    return $this;
  }

  public function getWarnings() {
    return $this->warnings;
  }

  public function setDocblockRaw($docblock_raw) {
    $this->docblockRaw = $docblock_raw;

    $parser = new PhutilDocblockParser();
    list($text, $meta) = $parser->parse($docblock_raw);
    $this->docblockText = $text;
    $this->docblockMeta = $meta;

    return $this;
  }

  public function getDocblockRaw() {
    return $this->docblockRaw;
  }

  public function getDocblockText() {
    if ($this->docblockText === null) {
      throw new Exception("Call setDocblockRaw() before getDocblockText()!");
    }
    return $this->docblockText;
  }

  public function getDocblockMeta() {
    if ($this->docblockMeta === null) {
      throw new Exception("Call setDocblockRaw() before getDocblockMeta()!");
    }
    return $this->docblockMeta;
  }

  public function getDocblockMetaValue($key, $default = null) {
    $meta = $this->getDocblockMeta();
    return idx($meta, $key, $default);
  }

  public function setDocblockMetaValue($key, $value) {
    $meta = $this->getDocblockMeta();
    $meta[$key] = $value;
    $this->docblockMeta = $meta;
    return $this;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setFile($file) {
    $this->file = $file;
    return $this;
  }

  public function getFile() {
    return $this->file;
  }

  public function setLine($line) {
    $this->line = $line;
    return $this;
  }

  public function getLine() {
    return $this->line;
  }

  public function setContentRaw($content_raw) {
    $this->contentRaw = $content_raw;
    return $this;
  }

  public function getContentRaw() {
    return $this->contentRaw;
  }

  public function setHash($hash) {
    $this->hash = $hash;
    return $this;
  }

  public function addLink(DivinerAtomRef $ref) {
    $this->links[] = $ref;
    return $this;
  }

  public function addExtends(DivinerAtomRef $ref) {
    $this->extends[] = $ref;
    return $this;
  }

  public function getLinkDictionaries() {
    return mpull($this->links, 'toDictionary');
  }

  public function getExtendsDictionaries() {
    return mpull($this->extends, 'toDictionary');
  }

  public function getHash() {
    if ($this->hash) {
      return $this->hash;
    }

    $parts = array(
      $this->getType(),
      $this->getName(),
      $this->getFile(),
      $this->getLine(),
      $this->getLength(),
      $this->getLanguage(),
      $this->getContentRaw(),
      $this->getDocblockRaw(),
      mpull($this->extends, 'toHash'),
      mpull($this->links, 'toHash'),
    );

    return md5(serialize($parts)).'N';
  }

  public function setLength($length) {
    $this->length = $length;
    return $this;
  }

  public function getLength() {
    return $this->length;
  }

  public function setLanguage($language) {
    $this->language = $language;
    return $this;
  }

  public function getLanguage() {
    return $this->language;
  }

  public function addChildHash($child_hash) {
    $this->childHashes[] = $child_hash;
    return $this;
  }

  public function getChildHashes() {
    return $this->childHashes;
  }

  public function setParentHash($parent_hash) {
    if ($this->parentHash) {
      throw new Exception("Atom already has a parent!");
    }
    $this->parentHash = $parent_hash;
    return $this;
  }

  public function getParentHash() {
    return $this->parentHash;
  }

  public function addChild(DivinerAtom $atom) {
    $atom->setParentHash($this->getHash());
    $this->addChildHash($atom->getHash());
    return $this;
  }

  public function getURI() {
    $parts = array();
    $parts[] = phutil_escape_uri_path_component($this->getType());
    if ($this->getContext()) {
      $parts[] = phutil_escape_uri_path_component($this->getContext());
    }
    $parts[] = phutil_escape_uri_path_component($this->getName());
    $parts[] = null;
    return implode('/', $parts);
  }


  public function toDictionary() {
    // NOTE: If you change this format, bump the format version in
    // getAtomSerializationVersion().

    return array(
      'book'        => $this->getBook(),
      'type'        => $this->getType(),
      'name'        => $this->getName(),
      'file'        => $this->getFile(),
      'line'        => $this->getLine(),
      'hash'        => $this->getHash(),
      'uri'         => $this->getURI(),
      'length'      => $this->getLength(),
      'context'     => $this->getContext(),
      'language'    => $this->getLanguage(),
      'docblockRaw' => $this->getDocblockRaw(),
      'warnings'    => $this->getWarnings(),
      'parentHash'  => $this->getParentHash(),
      'childHashes' => $this->getChildHashes(),
      'extends'     => $this->getExtendsDictionaries(),
      'links'       => $this->getLinkDictionaries(),
      'ref'         => $this->getRef()->toDictionary(),
    );
  }

  public function getRef() {
    $group = null;
    $title = null;
    if ($this->docblockMeta) {
      $group = $this->getDocblockMetaValue('group');
      $title = $this->getDocblockMetaValue('title');
    }

    return id(new DivinerAtomRef())
      ->setBook($this->getBook())
      ->setContext($this->getContext())
      ->setType($this->getType())
      ->setName($this->getName())
      ->setTitle($title)
      ->setGroup($group);
  }

  public static function newFromDictionary(array $dictionary) {
    $atom = id(new DivinerAtom())
      ->setBook(idx($dictionary, 'book'))
      ->setType(idx($dictionary, 'type'))
      ->setName(idx($dictionary, 'name'))
      ->setFile(idx($dictionary, 'file'))
      ->setLine(idx($dictionary, 'line'))
      ->setHash(idx($dictionary, 'hash'))
      ->setLength(idx($dictionary, 'length'))
      ->setContext(idx($dictionary, 'context'))
      ->setLanguage(idx($dictionary, 'language'))
      ->setParentHash(idx($dictionary, 'parentHash'))
      ->setDocblockRaw(idx($dictionary, 'docblockRaw'));

    foreach (idx($dictionary, 'warnings', array()) as $warning) {
      $atom->addWarning($warning);
    }

    foreach (idx($dictionary, 'childHashes', array()) as $child) {
      $atom->addChildHash($child);
    }

    return $atom;
  }

}
