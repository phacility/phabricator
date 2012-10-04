<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
