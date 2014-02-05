<?php

/**
 * Obsolete storage for saved search parameters. This class is no longer used;
 * it was obsoleted by the introduction of {@class:PhabricatorSavedQuery}.
 *
 * This class is retained only because one of the migrations
 * (`20130913.maniphest.1.migratesearch.php`) relies on it to migrate old saved
 * Maniphest searches to new infrastructure. We can remove this class and the
 * corresponding migration after installs have had a reasonable amount of time
 * to perform it.
 *
 * TODO: Remove this class after 2014-09-13, roughly.
 *
 * @deprecated
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
