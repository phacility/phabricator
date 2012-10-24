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
 * Shared interface used by Differential and Diffusion inline comments.
 */
interface PhabricatorInlineCommentInterface extends PhabricatorMarkupInterface {

  const MARKUP_FIELD_BODY = 'markup:body';

  public function setChangesetID($id);
  public function getChangesetID();

  public function setIsNewFile($is_new);
  public function getIsNewFile();

  public function setLineNumber($number);
  public function getLineNumber();

  public function setLineLength($length);
  public function getLineLength();

  public function setContent($content);
  public function getContent();

  public function setCache($cache);
  public function getCache();

  public function setAuthorPHID($phid);
  public function getAuthorPHID();

  public function setSyntheticAuthor($synthetic_author);
  public function getSyntheticAuthor();

  public function isCompatible(PhabricatorInlineCommentInterface $inline);
  public function isDraft();

  public function save();
  public function delete();

}
