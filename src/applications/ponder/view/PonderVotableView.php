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

final class PonderVotableView extends AphrontView {

  private $phid;
  private $uri;
  private $count;
  private $vote;

  public function setPHID($phid) {
    $this->phid = $phid;
    return $this;
  }

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function setCount($count) {
    $this->count = $count;
    return $this;
  }

  public function setVote($vote) {
    $this->vote = $vote;
    return $this;
  }

  public function render() {
    require_celerity_resource('ponder-vote-css');
    require_celerity_resource('javelin-behavior-ponder-votebox');

    Javelin::initBehavior(
      'ponder-votebox',
      array(
        'nodeid' => $this->phid,
        'vote' => $this->vote,
        'count' => $this->count,
        'uri' => $this->uri
      ));

    $content =
      '<div class="ponder-votable">'.
        '<div id="'.phutil_escape_html($this->phid).'" class="ponder-votebox">
         </div>'.
        $this->renderChildren().
        '<div class="ponder-votable-bottom"></div>'.
      '</div>';

    return $content;
  }

}
