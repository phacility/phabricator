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

final class PhabricatorRepositoryCommitData extends PhabricatorRepositoryDAO {

  const SUMMARY_MAX_LENGTH = 100;

  protected $commitID;
  protected $authorName    = '';
  protected $commitMessage = '';
  protected $commitDetails = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_SERIALIZATION => array(
        'commitDetails' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function getSummary() {
    $message = $this->getCommitMessage();
    $lines = explode("\n", $message);
    $summary = head($lines);

    $summary = phutil_utf8_shorten($summary, self::SUMMARY_MAX_LENGTH);

    return $summary;
  }

  public function getCommitDetail($key, $default = null) {
    return idx($this->commitDetails, $key, $default);
  }

  public function setCommitDetail($key, $value) {
    $this->commitDetails[$key] = $value;
    return $this;
  }

}
