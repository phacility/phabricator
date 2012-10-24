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

final class PhabricatorAuditInlineComment
  extends PhabricatorAuditDAO
  implements PhabricatorInlineCommentInterface {

  protected $commitPHID;
  protected $pathID;
  protected $auditCommentID;

  protected $authorPHID;
  protected $isNewFile;
  protected $lineNumber;
  protected $lineLength;
  protected $content;
  protected $cache;

  private $syntheticAuthor;

  public function setSyntheticAuthor($synthetic_author) {
    $this->syntheticAuthor = $synthetic_author;
    return $this;
  }

  public function getSyntheticAuthor() {
    return $this->syntheticAuthor;
  }

  public function isCompatible(PhabricatorInlineCommentInterface $comment) {
    return
      ($this->getAuthorPHID() === $comment->getAuthorPHID()) &&
      ($this->getSyntheticAuthor() === $comment->getSyntheticAuthor()) &&
      ($this->getContent() === $comment->getContent());
  }

  public function setContent($content) {
    $this->setCache(null);
    $this->writeField('content', $content);
    return $this;
  }

  public function getContent() {
    return $this->readField('content');
  }

  public function isDraft() {
    return !$this->getAuditCommentID();
  }

  public function setChangesetID($id) {
    return $this->setPathID($id);
  }

  public function getChangesetID() {
    return $this->getPathID();
  }

  // NOTE: We need to provide implementations so we conform to the shared
  // interface; these are all trivial and just explicit versions of the Lisk
  // defaults.

  public function setIsNewFile($is_new) {
    $this->writeField('isNewFile', $is_new);
    return $this;
  }

  public function getIsNewFile() {
    return $this->readField('isNewFile');
  }

  public function setLineNumber($number) {
    $this->writeField('lineNumber', $number);
    return $this;
  }

  public function getLineNumber() {
    return $this->readField('lineNumber');
  }

  public function setLineLength($length) {
    $this->writeField('lineLength', $length);
    return $this;
  }

  public function getLineLength() {
    return $this->readField('lineLength');
  }

  public function setCache($cache) {
    $this->writeField('cache', $cache);
    return $this;
  }

  public function getCache() {
    return $this->readField('cache');
  }

  public function setAuthorPHID($phid) {
    $this->writeField('authorPHID', $phid);
    return $this;
  }

  public function getAuthorPHID() {
    return $this->readField('authorPHID');
  }

/* -(  PhabricatorMarkupInterface Implementation  )-------------------------- */


  public function getMarkupFieldKey($field) {
    return 'AI:'.$this->getID();
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newDifferentialMarkupEngine();
  }

  public function getMarkupText($field) {
    return $this->getContent();
  }

  public function didMarkupText($field, $output, PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    // Only cache submitted comments.
    return ($this->getID() && $this->getAuditCommentID());
  }

}
