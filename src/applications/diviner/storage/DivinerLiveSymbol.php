<?php

final class DivinerLiveSymbol extends DivinerDAO
  implements PhabricatorPolicyInterface {

  protected $phid;
  protected $bookPHID;
  protected $context;
  protected $type;
  protected $name;
  protected $atomIndex;
  protected $graphHash;
  protected $identityHash;

  protected $title;
  protected $groupName;
  protected $summary;
  protected $isDocumentable = 0;

  private $book;
  private $content;
  private $atom;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_ATOM);
  }

  public function getBook() {
    if ($this->book === null) {
      throw new Exception("Call attachBook() before getBook()!");
    }
    return $this->book;
  }

  public function attachBook(DivinerLiveBook $book) {
    $this->book = $book;
    return $this;
  }

  public function getContent() {
    if ($this->content === null) {
      throw new Exception("Call attachAtom() before getContent()!");
    }
    return $this->content;
  }

  public function getAtom() {
    if ($this->atom === null) {
      throw new Exception("Call attachAtom() before getAtom()!");
    }
    return $this->atom;
  }

  public function attachAtom(DivinerLiveAtom $atom) {
    $this->content = $atom->getContent();
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

}
