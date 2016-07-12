<?php

final class DivinerSectionView extends AphrontTagView {

  private $header;
  private $content;

  public function addContent($content) {
    $this->content[] = $content;
    return $this;
  }

  public function setHeader($text) {
    $this->header = $text;
    return $this;
  }

  protected function getTagName() {
    return 'div';
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'diviner-document-section',
    );
  }

  protected function getTagContent() {
    require_celerity_resource('diviner-shared-css');

    $header = id(new PHUIHeaderView())
      ->setBleedHeader(true)
      ->addClass('diviner-section-header')
      ->setHeader($this->header);

    $content = phutil_tag_div('diviner-section-content', $this->content);

    return array($header, $content);
  }

}
