<?php

final class PhamePostView extends AphrontView {

  private $post;
  private $author;
  private $body;
  private $skin;
  private $summary;


  public function setSkin(PhameBlogSkin $skin) {
    $this->skin = $skin;
    return $this;
  }

  public function getSkin() {
    return $this->skin;
  }

  public function setAuthor(PhabricatorObjectHandle $author) {
    $this->author = $author;
    return $this;
  }

  public function getAuthor() {
    return $this->author;
  }

  public function setPost(PhamePost $post) {
    $this->post = $post;
    return $this;
  }

  public function getPost() {
    return $this->post;
  }

  public function setBody($body) {
    $this->body = $body;
    return $this;
  }

  public function getBody() {
    return $this->body;
  }

  public function setSummary($summary) {
    $this->summary = $summary;
    return $this;
  }

  public function getSummary() {
    return $this->summary;
  }

  public function renderTitle() {
    $href = $this->getSkin()->getURI('post/'.$this->getPost()->getPhameTitle());
    return phutil_tag(
      'h2',
      array(
        'class' => 'phame-post-title',
      ),
      phutil_tag(
        'a',
        array(
          'href' => $href,
        ),
        $this->getPost()->getTitle()));
  }

  public function renderDatePublished() {
    return phutil_tag(
      'div',
      array(
        'class' => 'phame-post-date',
      ),
        pht(
          'Published on %s by %s',
          phabricator_datetime(
            $this->getPost()->getDatePublished(),
            $this->getUser()),
          $this->getAuthor()->getName()));
  }

  public function renderBody() {
    return phutil_tag(
      'div',
      array(
        'class' => 'phame-post-body phabricator-remarkup',
      ),
      $this->getBody());
  }

  public function renderSummary() {
    return phutil_tag(
      'div',
      array(
        'class' => 'phame-post-body phabricator-remarkup',
      ),
      $this->getSummary());
  }

  public function render() {
    return phutil_tag(
      'div',
      array(
        'class' => 'phame-post',
      ),
      array(
        $this->renderTitle(),
        $this->renderDatePublished(),
        $this->renderBody(),
      ));
  }

  public function renderWithSummary() {
    return phutil_tag(
      'div',
      array(
        'class' => 'phame-post',
      ),
      array(
        $this->renderTitle(),
        $this->renderDatePublished(),
        $this->renderSummary(),
      ));
  }

}
