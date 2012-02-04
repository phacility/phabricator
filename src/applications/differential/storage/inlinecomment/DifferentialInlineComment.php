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

class DifferentialInlineComment extends DifferentialDAO {

  protected $revisionID;
  protected $commentID;
  protected $authorPHID;

  protected $changesetID;
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

  public function isCompatible(DifferentialInlineComment $comment) {
    return
      $this->authorPHID === $comment->authorPHID &&
      $this->syntheticAuthor === $comment->syntheticAuthor &&
      $this->content === $comment->content;
  }

}
