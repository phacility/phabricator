<?php

final class PhabricatorTransactionView extends AphrontView {

  private $imageURI;
  private $actions = array();
  private $epoch;
  private $contentSource;
  private $anchorName;
  private $anchorText;
  private $isPreview;
  private $classes = array();

  public function setImageURI($uri) {
    $this->imageURI = $uri;
    return $this;
  }

  public function setActions(array $actions) {
    $this->actions = $actions;
    return $this;
  }

  public function setEpoch($epoch) {
    $this->epoch = $epoch;
    return $this;
  }

  public function setContentSource(PhabricatorContentSource $source) {
    $this->contentSource = $source;
    return $this;
  }

  public function setAnchor($anchor_name, $anchor_text) {
    $this->anchorName = $anchor_name;
    $this->anchorText = $anchor_text;
    return $this;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function setIsPreview($preview) {
    $this->isPreview = $preview;
    return $this;
  }

  public function render() {
    if (!$this->user) {
      throw new Exception(pht("Call setUser() before render()!"));
    }

    require_celerity_resource('phabricator-transaction-view-css');

    $info = $this->renderTransactionInfo();
    $actions = $this->renderTransactionActions();
    $style = $this->renderTransactionStyle();
    $content = $this->renderTransactionContent();
    $classes = implode(' ', $this->classes);

    $transaction_id = $this->anchorName ? 'anchor-'.$this->anchorName : null;

    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-transaction-view',
        'id'    => $transaction_id,
        'style' => $style,
      ),
      hsprintf(
        '<div class="phabricator-transaction-detail %s">'.
          '<div class="phabricator-transaction-header">%s%s</div>'.
          '%s'.
        '</div>',
        $classes,
        $info,
        $actions,
        $content));

  }

  private function renderTransactionInfo() {
    $info = array();

    if ($this->contentSource) {
      $content_source = new PhabricatorContentSourceView();
      $content_source->setContentSource($this->contentSource);
      $content_source->setUser($this->user);
      $source = $content_source->render();
      if ($source) {
        $info[] = $source;
      }
    }

    if ($this->isPreview) {
      $info[] = 'PREVIEW';
    } else if ($this->epoch) {
      $info[] = phabricator_datetime($this->epoch, $this->user);
    }

    if ($this->anchorName) {
      Javelin::initBehavior('phabricator-watch-anchor');

      $anchor = id(new PhabricatorAnchorView())
        ->setAnchorName($this->anchorName)
        ->render();

      $info[] = hsprintf(
        '%s%s',
        $anchor,
        phutil_tag(
          'a',
          array('href'  => '#'.$this->anchorName),
          $this->anchorText));
    }

    $info = phutil_implode_html(" \xC2\xB7 ", $info);

    return hsprintf(
      '<span class="phabricator-transaction-info">%s</span>',
      $info);
  }

  private function renderTransactionActions() {
    return $this->actions;
  }

  private function renderTransactionStyle() {
    if ($this->imageURI) {
      return 'background-image: url('.$this->imageURI.');';
    } else {
      return null;
    }
  }

  private function renderTransactionContent() {
    $content = $this->renderChildren();
    if ($this->isEmptyContent($content)) {
      return null;
    }
    return phutil_tag(
      'div',
      array('class' => 'phabricator-transaction-content'),
      $content);
  }

}
