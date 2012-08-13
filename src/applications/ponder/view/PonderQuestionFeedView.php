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

final class PonderQuestionFeedView extends AphrontView {
  private $user;
  private $offset;
  private $data;
  private $pagesize;
  private $uri;
  private $param;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setOffset($offset) {
    $this->offset = $offset;
    return $this;
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function setPageSize($pagesize) {
    $this->pagesize = $pagesize;
    return $this;
  }

  public function setHandles($handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setURI($uri, $param) {
    $this->uri = $uri;
    $this->param = $param;
    return $this;
  }

  public function render() {
    require_celerity_resource('ponder-core-view-css');
    require_celerity_resource('ponder-feed-view-css');

    $user = $this->user;
    $offset = $this->offset;
    $data = $this->data;
    $handles = $this->handles;
    $uri = $this->uri;
    $param = $this->param;
    $pagesize = $this->pagesize;

    $panel = id(new AphrontPanelView())
      ->setHeader("Popular Questions")
      ->addClass("ponder-panel");

    $panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => "/ponder/question/ask/",
          'class' => 'green button',
        ),
        "Ask a question"));

    $pagebuttons = id(new AphrontPagerView())
      ->setPageSize($pagesize)
      ->setOffset($offset)
      ->setURI($uri, $param);

    $data = $pagebuttons->sliceResults($data);


    foreach ($data as $question) {
      $cur = id(new PonderQuestionSummaryView())
        ->setUser($user)
        ->setQuestion($question)
        ->setHandles($handles);
      $panel->appendChild($cur);
    }

    $panel->appendChild($pagebuttons);
    return $panel->render();
  }
}
