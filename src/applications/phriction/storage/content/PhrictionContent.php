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
 * @group phriction
 */
class PhrictionContent extends PhrictionDAO {

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
    $engine = PhabricatorMarkupEngine::newPhrictionMarkupEngine();
    $markup = $engine->markupText($this->getContent());

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
        $markup.
      '</div>';
  }

}
