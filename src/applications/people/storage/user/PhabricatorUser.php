<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhabricatorUser extends PhabricatorUserDAO {

  const PHID_TYPE = 'USER';

  protected $phid;
  protected $userName;
  protected $realName;
  protected $email;
  protected $passwordSalt;
  protected $passwordHash;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(self::PHID_TYPE);
  }

  public function setPassword($password) {
    $this->setPasswordSalt(md5(mt_rand()));
    $hash = $this->hashPassword($password);
    $this->setPasswordHash($hash);
    return $this;
  }

  public function comparePassword($password) {
    $password = $this->hashPassword($password);
    return ($password === $this->getPasswordHash());
  }

  private function hashPassword($password) {
    $password = $this->getUsername().
                $password.
                $this->getPHID().
                $this->getPasswordSalt();
    for ($ii = 0; $ii < 1000; $ii++) {
      $password = md5($password);
    }
    return $password;
  }

}
