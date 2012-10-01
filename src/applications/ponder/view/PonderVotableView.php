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

    Javelin::initBehavior('ponder-votebox', array());

    $uri = id(new PhutilURI($this->uri))->alter('phid', $this->phid);

    $up = javelin_render_tag(
      'a',
      array(
        'href'        => (string)$uri,
        'sigil'       => 'upvote',
        'mustcapture' => true,
        'class'       => ($this->vote > 0) ? 'ponder-vote-active' : null,
      ),
      "\xE2\x96\xB2");

    $down = javelin_render_tag(
      'a',
      array(
        'href'        => (string)$uri,
        'sigil'       => 'downvote',
        'mustcapture' => true,
        'class'       => ($this->vote < 0) ? 'ponder-vote-active' : null,
      ),
      "\xE2\x96\xBC");

    $count = javelin_render_tag(
      'div',
      array(
        'class'       => 'ponder-vote-count',
        'sigil'       => 'ponder-vote-count',
      ),
      phutil_escape_html($this->count));

    return javelin_render_tag(
      'div',
      array(
        'class' => 'ponder-votable',
        'sigil' => 'ponder-votable',
        'meta' => array(
          'count' => (int)$this->count,
          'vote'  => (int)$this->vote,
        ),
      ),
      javelin_render_tag(
        'div',
        array(
          'class' => 'ponder-votebox',
        ),
        $up.$count.$down).
      phutil_render_tag(
        'div',
        array(
          'class' => 'ponder-votebox-content',
        ),
        $this->renderChildren()));
  }

}
