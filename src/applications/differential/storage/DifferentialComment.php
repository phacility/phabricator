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

final class DifferentialComment extends DifferentialDAO
  implements PhabricatorMarkupInterface {

  const METADATA_ADDED_REVIEWERS   = 'added-reviewers';
  const METADATA_REMOVED_REVIEWERS = 'removed-reviewers';
  const METADATA_ADDED_CCS         = 'added-ccs';
  const METADATA_DIFF_ID           = 'diff-id';

  const MARKUP_FIELD_BODY          = 'markup:body';

  protected $authorPHID;
  protected $revisionID;
  protected $action;
  protected $content;
  protected $cache;
  protected $metadata = array();
  protected $contentSource;

  private $arbitraryDiffForFacebook;

  public function giveFacebookSomeArbitraryDiff(DifferentialDiff $diff) {
    $this->arbitraryDiffForFacebook = $diff;
    return $this;
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source->serialize();
    return $this;
  }

  public function getContentSource() {
    return PhabricatorContentSource::newFromSerialized($this->contentSource);
  }


  public function getMarkupFieldKey($field) {
    if ($this->getID()) {
      return 'DC:'.$this->getID();
    }

    // The summary and test plan render as comments, but do not have IDs.
    // They are also mutable. Build keys using content hashes.
    $hash = PhabricatorHash::digest($this->getMarkupText($field));
    return 'DC:'.$hash;
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newDifferentialMarkupEngine(
      array(
        'differential.diff' => $this->arbitraryDiffForFacebook,
      ));
  }

  public function getMarkupText($field) {
    return $this->getContent();
  }

  public function didMarkupText($field, $output, PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    if ($this->getID()) {
      return true;
    }

    $action = $this->getAction();
    switch ($action) {
      case DifferentialAction::ACTION_SUMMARIZE:
      case DifferentialAction::ACTION_TESTPLAN:
        return true;
    }

    return false;
  }

}
