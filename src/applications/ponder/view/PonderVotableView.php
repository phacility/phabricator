<?php

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

    $up = javelin_tag(
      'a',
      array(
        'href'        => (string)$uri,
        'sigil'       => 'upvote',
        'mustcapture' => true,
        'class'       => ($this->vote > 0) ? 'ponder-vote-active' : null,
      ),
      "\xE2\x96\xB2");

    $down = javelin_tag(
      'a',
      array(
        'href'        => (string)$uri,
        'sigil'       => 'downvote',
        'mustcapture' => true,
        'class'       => ($this->vote < 0) ? 'ponder-vote-active' : null,
      ),
      "\xE2\x96\xBC");

    $count = javelin_tag(
      'div',
      array(
        'class'       => 'ponder-vote-count',
        'sigil'       => 'ponder-vote-count',
      ),
      $this->count);

    return javelin_tag(
      'div',
      array(
        'class' => 'ponder-votable',
        'sigil' => 'ponder-votable',
        'meta' => array(
          'count' => (int)$this->count,
          'vote'  => (int)$this->vote,
        ),
      ),
      array(
        javelin_tag(
          'div',
          array(
            'class' => 'ponder-votebox',
          ),
          array($up, $count, $down)),
        phutil_tag(
          'div',
          array(
            'class' => 'ponder-votebox-content',
          ),
          $this->renderChildren()),
      ));
  }

}
