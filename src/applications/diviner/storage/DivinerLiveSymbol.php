<?php

final class DivinerLiveSymbol extends DivinerDAO
  implements PhabricatorPolicyInterface, PhabricatorMarkupInterface {

  protected $bookPHID;
  protected $context;
  protected $type;
  protected $name;
  protected $atomIndex;
  protected $graphHash;
  protected $identityHash;
  protected $nodeHash;

  protected $title;
  protected $titleSlugHash;
  protected $groupName;
  protected $summary;
  protected $isDocumentable = 0;

  private $book = self::ATTACHABLE;
  private $atom = self::ATTACHABLE;
  private $extends = self::ATTACHABLE;
  private $children = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      DivinerPHIDTypeAtom::TYPECONST);
  }

  public function getBook() {
    return $this->assertAttached($this->book);
  }

  public function attachBook(DivinerLiveBook $book) {
    $this->book = $book;
    return $this;
  }

  public function getAtom() {
    return $this->assertAttached($this->atom);
  }

  public function attachAtom(DivinerLiveAtom $atom) {
    $this->atom = DivinerAtom::newFromDictionary($atom->getAtomData());
    return $this;
  }

  public function getURI() {
    $parts = array(
      'book',
      $this->getBook()->getName(),
      $this->getType(),
    );

    if ($this->getContext()) {
      $parts[] = $this->getContext();
    }

    $parts[] = $this->getName();

    if ($this->getAtomIndex()) {
      $parts[] = $this->getAtomIndex();
    }

    return '/'.implode('/', $parts).'/';
  }

  public function getSortKey() {
    return $this->getTitle();
  }

  public function save() {

    // NOTE: The identity hash is just a sanity check because the unique tuple
    // on this table is way way too long to fit into a normal UNIQUE KEY. We
    // don't use it directly, but its existence prevents duplicate records.

    if (!$this->identityHash) {
      $this->identityHash = PhabricatorHash::digestForIndex(
        serialize(
          array(
            'bookPHID' => $this->getBookPHID(),
            'context'  => $this->getContext(),
            'type'     => $this->getType(),
            'name'     => $this->getName(),
            'index'    => $this->getAtomIndex(),
          )));
    }

    return parent::save();
  }

  public function getTitle() {
    $title = parent::getTitle();
    if (!strlen($title)) {
      $title = $this->getName();
    }
    return $title;
  }

  public function setTitle($value) {
    $this->writeField('title', $value);
    if (strlen($value)) {
      $slug = DivinerAtomRef::normalizeTitleString($value);
      $hash = PhabricatorHash::digestForIndex($slug);
      $this->titleSlugHash = $hash;
    } else {
      $this->titleSlugHash = null;
    }
    return $this;
  }

  public function attachExtends(array $extends) {
    assert_instances_of($extends, 'DivinerLiveSymbol');
    $this->extends = $extends;
    return $this;
  }

  public function getExtends() {
    return $this->assertAttached($this->extends);
  }

  public function attachChildren(array $children) {
    assert_instances_of($children, 'DivinerLiveSymbol');
    $this->children = $children;
    return $this;
  }

  public function getChildren() {
    return $this->assertAttached($this->children);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */

  public function getCapabilities() {
    return $this->getBook()->getCapabilities();
  }


  public function getPolicy($capability) {
    return $this->getBook()->getPolicy($capability);
  }


  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getBook()->hasAutomaticCapability($capability, $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht('Atoms inherit the policies of the books they are part of.');
  }


/* -(  Markup Interface  )--------------------------------------------------- */


  public function getMarkupFieldKey($field) {
    return $this->getPHID().':'.$field.':'.$this->getGraphHash();
  }


  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::getEngine('diviner');
  }


  public function getMarkupText($field) {
    return $this->getAtom()->getDocblockText();
  }


  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {
    return $output;
  }


  public function shouldUseMarkupCache($field) {
    return true;
  }

}
