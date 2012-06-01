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
