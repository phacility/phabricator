<?php

/**
 * @group search
 */
final class PhabricatorSavedQuery extends PhabricatorSearchDAO {

  protected $parameters = array();
  protected $queryKey = "";
  protected $engineClassName = "PhabricatorPasteSearchEngine";

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'parameters' => self::SERIALIZATION_JSON), )
      + parent::getConfiguration();
  }

  public function setParameter($key, $value) {
    $this->parameters[$key] = $value;
    return $this;
  }

  public function getParameter($key, $default = null) {
    return idx($this->parameters, $key, $default);
  }

  public function save() {
    if ($this->getEngineClass() === null) {
      throw new Exception(pht("Engine class is null."));
    }

    $serial = $this->getEngineClass().serialize($this->parameters);
    $this->queryKey = PhabricatorHash::digestForIndex($serial);

    return parent::save();
  }
}
