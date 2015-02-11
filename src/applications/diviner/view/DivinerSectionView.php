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
      ->setHeader($this->header);

    $content = id(new PHUIBoxView())
      ->addPadding(PHUI::PADDING_LARGE_LEFT)
      ->addPadding(PHUI::PADDING_LARGE_RIGHT)
      ->appendChild($this->content);

    return array($header, $content);
  }

}
