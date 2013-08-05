<?php

/**
 * Render a simple preview panel for a bound Remarkup text control.
 */
final class PHUIRemarkupPreviewPanel extends AphrontTagView {

  private $header;
  private $loadingText;
  private $controlID;
  private $previewURI;

  protected function canAppendChild() {
    return false;
  }

  public function setPreviewURI($preview_uri) {
    $this->previewURI = $preview_uri;
    return $this;
  }

  public function setControlID($control_id) {
    $this->controlID = $control_id;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setLoadingText($loading_text) {
    $this->loadingText = $loading_text;
    return $this;
  }

  public function getTagName() {
    return 'div';
  }

  public function getTagAttributes() {
    return array(
      'class' => 'phui-remarkup-preview',
    );
  }

  protected function getTagContent() {
    if ($this->previewURI === null) {
      throw new Exception("Call setPreviewURI() before rendering!");
    }
    if ($this->controlID === null) {
      throw new Exception("Call setControlID() before rendering!");
    }

    $preview_id = celerity_generate_unique_node_id();

    require_celerity_resource('phui-remarkup-preview-css');
    Javelin::initBehavior(
      'remarkup-preview',
      array(
        'previewID' => $preview_id,
        'controlID' => $this->controlID,
        'uri' => $this->previewURI,
      ));

    $loading = phutil_tag(
      'div',
      array(
        'class' => 'phui-preview-loading-text',
      ),
      nonempty($this->loadingText, pht('Loading preview...')));

    $header = null;
    if ($this->header) {
      $header = phutil_tag(
        'div',
        array(
          'class' => 'phui-preview-header',
        ),
        $this->header);
    }

    $preview = phutil_tag(
      'div',
      array(
        'id' => $preview_id,
        'class' => 'phabricator-remarkup',
      ),
      $loading);

    return array($header, $preview);
  }

}
