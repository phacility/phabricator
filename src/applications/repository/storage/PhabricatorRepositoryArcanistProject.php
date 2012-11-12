<?php

final class PhabricatorRepositoryArcanistProject
  extends PhabricatorRepositoryDAO {

  protected $name;
  protected $phid;
  protected $repositoryID;

  protected $symbolIndexLanguages = array();
  protected $symbolIndexProjects  = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID   => true,
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'symbolIndexLanguages' => self::SERIALIZATION_JSON,
        'symbolIndexProjects'  => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID('APRJ');
  }

  public function loadRepository() {
    if (!$this->getRepositoryID()) {
      return null;
    }
    return id(new PhabricatorRepository())->load($this->getRepositoryID());
  }

  public function delete() {
    $this->openTransaction();
      $conn_w = $this->establishConnection('w');

      $symbols = id(new PhabricatorRepositorySymbol())->loadAllWhere(
        'arcanistProjectID = %d',
        $this->getID()
      );
      foreach ($symbols as $symbol) {
        $symbol->delete();
      }

      $result = parent::delete();
    $this->saveTransaction();
    return $result;
  }

}
