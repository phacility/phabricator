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
 * @task markup Markup Interface
 *
 * @group phriction
 */
final class PhrictionContent extends PhrictionDAO
  implements PhabricatorMarkupInterface {

  const MARKUP_FIELD_BODY = 'markup:body';

  protected $id;
  protected $documentID;
  protected $version;
  protected $authorPHID;

  protected $title;
  protected $slug;
  protected $content;
  protected $description;

  protected $changeType;
  protected $changeRef;

  public function renderContent() {
    return PhabricatorMarkupEngine::renderOneObject(
      $this,
      self::MARKUP_FIELD_BODY);
  }


/* -(  Markup Interface  )--------------------------------------------------- */


  /**
   * @task markup
   */
  public function getMarkupFieldKey($field) {
    if ($this->shouldUseMarkupCache($field)) {
      $id = $this->getID();
    } else {
      $id = PhabricatorHash::digest($this->getMarkupText($field));
    }
    return "phriction:{$field}:{$id}";
  }


  /**
   * @task markup
   */
  public function getMarkupText($field) {
    return $this->getContent();
  }


  /**
   * @task markup
   */
  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newPhrictionMarkupEngine();
  }


  /**
   * @task markup
   */
  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {

    $toc = PhutilRemarkupEngineRemarkupHeaderBlockRule::renderTableOfContents(
      $engine);

    if ($toc) {
      $toc =
        '<div class="phabricator-remarkup-toc">'.
          '<div class="phabricator-remarkup-toc-header">'.
            'Table of Contents'.
          '</div>'.
          $toc.
        '</div>';
    }

    return
      '<div class="phabricator-remarkup">'.
        $toc.
        $output.
      '</div>';
  }


  /**
   * @task markup
   */
  public function shouldUseMarkupCache($field) {
    return (bool)$this->getID();
  }


}
