<?php

/**
 * @group search
 */
final class PhabricatorSearchQuery extends PhabricatorSearchDAO {

  protected $query;
  protected $parameters = array();
  protected $queryKey;

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'parameters' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function setParameter($parameter, $value) {
    $this->parameters[$parameter] = $value;
    return $this;
  }

  public function getParameter($parameter, $default = null) {
    return idx($this->parameters, $parameter, $default);
  }

  public function save() {
    if (!$this->getQueryKey()) {
      $this->setQueryKey(Filesystem::readRandomCharacters(12));
    }
    return parent::save();
  }

}
