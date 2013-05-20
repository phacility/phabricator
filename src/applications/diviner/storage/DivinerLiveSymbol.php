<?php

final class DivinerLiveSymbol extends DivinerDAO {

  protected $phid;
  protected $bookPHID;
  protected $context;
  protected $type;
  protected $name;
  protected $atomIndex;
  protected $graphHash;
  protected $identityHash;

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


}
