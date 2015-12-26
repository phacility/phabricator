<?php

final class PHUIDocumentSummaryView extends AphrontTagView {

  private $title;
  private $image;
  private $imageHref;
  private $subtitle;
  private $href;
  private $summary;
  private $draft;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setSubtitle($subtitle) {
    $this->subtitle = $subtitle;
    return $this;
  }

  public function setImage($image) {
    $this->image = $image;
    return $this;
  }

  public function setImageHref($image_href) {
    $this->imageHref = $image_href;
    return $this;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function setSummary($summary) {
    $this->summary = $summary;
    return $this;
  }

  public function setDraft($draft) {
    $this->draft = $draft;
    return $this;
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phui-document-summary-view';
    $classes[] = 'phabricator-remarkup';

    if ($this->draft) {
      $classes[] = 'is-draft';
    }

    return array(
      'class' => implode(' ', $classes),
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phui-document-summary-view-css');

    $title = phutil_tag(
      'a',
      array(
        'href' => $this->href,
      ),
      $this->title);

    $header = phutil_tag(
      'h2',
      array(
        'class' => 'remarkup-header',
      ),
      $title);

    $subtitle = phutil_tag(
      'div',
      array(
        'class' => 'phui-document-summary-subtitle',
      ),
      $this->subtitle);

    $body = phutil_tag(
      'div',
      array(
        'class' => 'phui-document-summary-body',
      ),
      $this->summary);

    $read_more = phutil_tag(
      'a',
      array(
        'class' => 'phui-document-read-more',
        'href' => $this->href,
      ),
      pht('Read more...'));

    return array($header, $subtitle, $body, $read_more);
  }

}
