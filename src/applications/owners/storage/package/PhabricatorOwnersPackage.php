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

class PhabricatorOwnersPackage extends PhabricatorOwnersDAO {

  protected $phid;
  protected $name;
  protected $description;
  protected $primaryOwnerPHID;

  public function getConfiguration() {
    return array(
      // This information is better available from the history table.
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_AUX_PHID   => true,
    );
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNew('OPKG');
  }

}
