<?php

final class PhabricatorConfigPageView extends AphrontTagView {

  private $header;
  private $content;
  private $footer;

  public function setHeader(PHUIHeaderView $header) {
    $this->header = $header;
    return $this;
  }

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function setFooter($footer) {
    $this->footer = $footer;
    return $this;
  }

  protected function getTagName() {
    return 'div';
  }

  protected function getTagAttributes() {
    require_celerity_resource('config-page-css');

    $classes = array();
    $classes[] = 'config-page';

    return array(
      'class' => implode(' ', $classes),
    );
  }

  protected function getTagContent() {

    $header = null;
    if ($this->header) {
      $header = phutil_tag_div('config-page-header', $this->header);
    }

    $content = null;
    if ($this->content) {
      $content = phutil_tag_div('config-page-content', $this->content);
    }

    $footer = null;
    if ($this->footer) {
      $footer = phutil_tag_div('config-page-footer', $this->footer);
    }

    return array($header, $content, $footer);

  }

}
