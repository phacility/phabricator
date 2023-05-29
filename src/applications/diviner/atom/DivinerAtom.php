<?php

final class DivinerAtom extends Phobject {

  const TYPE_ARTICLE   = 'article';
  const TYPE_CLASS     = 'class';
  const TYPE_FILE      = 'file';
  const TYPE_FUNCTION  = 'function';
  const TYPE_INTERFACE = 'interface';
  const TYPE_METHOD    = 'method';

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
  private $parent;
  private $parentHash;
  private $children = array();
  private $childHashes = array();
  private $context;
  private $extends = array();
  private $links = array();
  private $book;
  private $properties = array();

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
        sprintf('%08d', $this->getLine()),
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
    return 2;
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

    if ($docblock_raw !== null) {
      $parser = new PhutilDocblockParser();
      list($text, $meta) = $parser->parse($docblock_raw);
      $this->docblockText = $text;
      $this->docblockMeta = $meta;
    } else {
      $this->docblockText = null;
      $this->docblockMeta = null;
    }

    return $this;
  }

  public function getDocblockRaw() {
    return $this->docblockRaw;
  }

  public function getDocblockText() {
    if ($this->docblockText === null) {
      throw new PhutilInvalidStateException('setDocblockRaw');
    }
    return $this->docblockText;
  }

  public function getDocblockMeta() {
    if ($this->docblockMeta === null) {
      throw new PhutilInvalidStateException('setDocblockRaw');
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

  public function getExtends() {
    return $this->extends;
  }

  public function getHash() {
    if ($this->hash) {
      return $this->hash;
    }

    $parts = array(
      $this->getBook(),
      $this->getType(),
      $this->getName(),
      $this->getFile(),
      $this->getLine(),
      $this->getLength(),
      $this->getLanguage(),
      $this->getContentRaw(),
      $this->getDocblockRaw(),
      $this->getProperties(),
      $this->getChildHashes(),
      mpull($this->extends, 'toHash'),
      mpull($this->links, 'toHash'),
    );

    $this->hash = md5(serialize($parts)).'N';
    return $this->hash;
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
    if (!$this->childHashes && $this->children) {
      $this->childHashes = mpull($this->children, 'getHash');
    }
    return $this->childHashes;
  }

  public function setParentHash($parent_hash) {
    if ($this->parentHash) {
      throw new Exception(pht('Atom already has a parent!'));
    }
    $this->parentHash = $parent_hash;
    return $this;
  }

  public function hasParent() {
    return $this->parent || $this->parentHash;
  }

  public function setParent(DivinerAtom $atom) {
    if ($this->parentHash) {
      throw new Exception(pht('Parent hash has already been computed!'));
    }
    $this->parent = $atom;
    return $this;
  }

  public function getParentHash() {
    if ($this->parent && !$this->parentHash) {
      $this->parentHash = $this->parent->getHash();
    }
    return $this->parentHash;
  }

  public function addChild(DivinerAtom $atom) {
    if ($this->childHashes) {
      throw new Exception(pht('Child hashes have already been computed!'));
    }

    $atom->setParent($this);
    $this->children[] = $atom;
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
    // @{method:getAtomSerializationVersion}.
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
      'properties'  => $this->getProperties(),
    );
  }

  public function getRef() {
    $title = null;
    if ($this->docblockMeta) {
      $title = $this->getDocblockMetaValue('title');
    }

    return id(new DivinerAtomRef())
      ->setBook($this->getBook())
      ->setContext($this->getContext())
      ->setType($this->getType())
      ->setName($this->getName())
      ->setTitle($title)
      ->setGroup($this->getProperty('group'));
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
      ->setDocblockRaw(idx($dictionary, 'docblockRaw'))
      ->setProperties(idx($dictionary, 'properties'));

    foreach (idx($dictionary, 'warnings', array()) as $warning) {
      $atom->addWarning($warning);
    }

    foreach (idx($dictionary, 'childHashes', array()) as $child) {
      $atom->addChildHash($child);
    }

    foreach (idx($dictionary, 'extends', array()) as $extends) {
      $atom->addExtends(DivinerAtomRef::newFromDictionary($extends));
    }

    return $atom;
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getProperties() {
    return $this->properties;
  }

  public function setProperties(array $properties) {
    $this->properties = $properties;
    return $this;
  }

  public static function getThisAtomIsNotDocumentedString($type) {
    switch ($type) {
      case self::TYPE_ARTICLE:
        return pht('This article is not documented.');
      case self::TYPE_CLASS:
        return pht('This class is not documented.');
      case self::TYPE_FILE:
        return pht('This file is not documented.');
      case self::TYPE_FUNCTION:
        return pht('This function is not documented.');
      case self::TYPE_INTERFACE:
        return pht('This interface is not documented.');
      case self::TYPE_METHOD:
        return pht('This method is not documented.');
      default:
        phlog(pht("Need translation for '%s'.", $type));
        return pht('This %s is not documented.', $type);
    }
  }

  public static function getAllTypes() {
    return array(
      self::TYPE_ARTICLE,
      self::TYPE_CLASS,
      self::TYPE_FILE,
      self::TYPE_FUNCTION,
      self::TYPE_INTERFACE,
      self::TYPE_METHOD,
    );
  }

  public static function getAtomTypeNameString($type) {
    switch ($type) {
      case self::TYPE_ARTICLE:
        return pht('Article');
      case self::TYPE_CLASS:
        return pht('Class');
      case self::TYPE_FILE:
        return pht('File');
      case self::TYPE_FUNCTION:
        return pht('Function');
      case self::TYPE_INTERFACE:
        return pht('Interface');
      case self::TYPE_METHOD:
        return pht('Method');
      default:
        phlog(pht("Need translation for '%s'.", $type));
        return ucwords($type);
    }
  }

}
