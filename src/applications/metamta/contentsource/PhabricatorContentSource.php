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

final class PhabricatorContentSource {

  const SOURCE_UNKNOWN  = 'unknown';
  const SOURCE_WEB      = 'web';
  const SOURCE_EMAIL    = 'email';
  const SOURCE_CONDUIT  = 'conduit';
  const SOURCE_MOBILE   = 'mobile';
  const SOURCE_TABLET   = 'tablet';
  const SOURCE_FAX      = 'fax';

  private $source;
  private $params = array();

  private function __construct() {
    // <empty>
  }

  public static function newForSource($source, array $params) {
    $obj = new PhabricatorContentSource();
    $obj->source = $source;
    $obj->params = $params;

    return $obj;
  }

  public static function newFromSerialized($serialized) {
    $dict = json_decode($serialized, true);
    if (!is_array($dict)) {
      $dict = array();
    }

    $obj = new PhabricatorContentSource();
    $obj->source = idx($dict, 'source', self::SOURCE_UNKNOWN);
    $obj->params = idx($dict, 'params', array());

    return $obj;
  }

  public function serialize() {
    return json_encode(array(
      'source' => $this->getSource(),
      'params' => $this->getParams(),
    ));
  }

  public function getSource() {
    return $this->source;
  }

  public function getParams() {
    return $this->params;
  }

  public function getParam($key, $default = null) {
    return idx($this->params, $key, $default);
  }

}
