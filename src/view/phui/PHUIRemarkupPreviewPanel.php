<?php

/**
 * Render a simple preview panel for a bound Remarkup text control.
 */
final class PHUIRemarkupPreviewPanel extends AphrontTagView {

  private $header;
  private $loadingText;
  private $controlID;
  private $previewURI;
  private $previewType;

  const DOCUMENT = 'document';

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

  public function setPreviewType($type) {
    $this->previewType = $type;
    return $this;
  }

  protected function getTagName() {
    return 'div';
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phui-remarkup-preview';

    return array(
      'class' => $classes,
    );
  }

  protected function getTagContent() {
    if ($this->previewURI === null) {
      throw new PhutilInvalidStateException('setPreviewURI');
    }
    if ($this->controlID === null) {
      throw new PhutilInvalidStateException('setControlID');
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

    $preview = phutil_tag(
      'div',
      array(
        'id' => $preview_id,
        'class' => 'phabricator-remarkup phui-preview-body',
      ),
      $loading);

    if (!$this->previewType) {
      $header = null;
      if ($this->header) {
        $header = phutil_tag(
          'div',
          array(
            'class' => 'phui-preview-header',
          ),
          $this->header);
      }
      $content = array($header, $preview);

    } else if ($this->previewType == self::DOCUMENT) {
      $header = id(new PHUIHeaderView())
        ->setHeader(pht('%s (Preview)', $this->header));

      $content = id(new PHUIDocumentView())
        ->setHeader($header)
        ->appendChild($preview);
    }

    return id(new PHUIObjectBoxView())
      ->appendChild($content)
      ->setCollapsed(true);
  }

}
