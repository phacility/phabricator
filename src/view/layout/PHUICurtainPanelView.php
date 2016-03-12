<?php

final class PHUICurtainPanelView extends AphrontTagView {

  private $order = 0;
  private $headerText;

  public function setHeaderText($header_text) {
    $this->headerText = $header_text;
    return $this;
  }

  public function getHeaderText() {
    return $this->headerText;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  public function getOrder() {
    return $this->order;
  }

  public function getOrderVector() {
    return id(new PhutilSortVector())
      ->addInt($this->getOrder());
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'phui-curtain-panel',
    );
  }

  protected function getTagContent() {
    $header = null;

    $header_text = $this->getHeaderText();
    if (strlen($header_text)) {
      $header = phutil_tag(
        'div',
        array(
          'class' => 'phui-curtain-panel-header',
        ),
        $header_text);
    }

    $body = phutil_tag(
      'div',
      array(
        'class' => 'phui-curtain-panel-body',
      ),
      $this->renderChildren());

    return array(
      $header,
      $body,
    );
  }

}
