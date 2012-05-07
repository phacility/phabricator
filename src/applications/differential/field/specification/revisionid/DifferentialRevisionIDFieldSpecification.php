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

final class DifferentialRevisionIDFieldSpecification
  extends DifferentialFieldSpecification {

  private $id;

  protected function didSetRevision() {
    $this->id = $this->getRevision()->getID();
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function shouldAppearOnCommitMessageTemplate() {
    return false;
  }

  public function getCommitMessageKey() {
    return 'revisionID';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->id = $value;
    return $this;
  }

  public function renderLabelForCommitMessage() {
    return 'Differential Revision';
  }

  public function renderValueForCommitMessage($is_edit) {
    if (!$this->id) {
      return null;
    }
    return PhabricatorEnv::getProductionURI('/D'.$this->id);
  }

  public function parseValueFromCommitMessage($value) {
    $rev = trim($value);

    if (!strlen($rev)) {
      return null;
    }

    if (is_numeric($rev)) {
      // TODO: Eventually, remove support for bare revision numbers.
      return (int)$rev;
    }

    $rev = self::parseRevisionIDFromURI($rev);
    if ($rev !== null) {
      return $rev;
    }

    $example_uri = PhabricatorEnv::getProductionURI('/D123');
    throw new DifferentialFieldParseException(
      "Commit references invalid 'Differential Revision'. Expected a ".
      "Phabricator URI like '{$example_uri}', got '{$value}'.");
  }

  public static function parseRevisionIDFromURI($uri) {
    $path = id(new PhutilURI($uri))->getPath();

    $matches = null;
    if (preg_match('#^/D(\d+)$#', $path, $matches)) {
      $id = (int)$matches[1];
      // Make sure the URI is the same as our URI. Basically, we want to ignore
      // commits from other Phabricator installs.
      if ($uri == PhabricatorEnv::getProductionURI('/D'.$id)) {
        return $id;
      }
    }

    return null;
  }

  public function shouldAppearOnRevisionList() {
    return true;
  }

  public function renderHeaderForRevisionList() {
    return 'ID';
  }

  public function renderValueForRevisionList(DifferentialRevision $revision) {
    return 'D'.$revision->getID();
  }

}
