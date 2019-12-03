<?php

final class PHUIPolicySectionView
  extends AphrontTagView {

  private $icon;
  private $header;
  private $documentationLink;

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function getHeader() {
    return $this->header;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function setDocumentationLink($name, $href) {
    $link = phutil_tag(
      'a',
      array(
        'href' => $href,
        'target' => '_blank',
      ),
      $name);

    $this->documentationLink = phutil_tag(
      'div',
      array(
        'class' => 'phui-policy-section-view-link',
      ),
      array(
        id(new PHUIIconView())->setIcon('fa-book'),
        $link,
      ));

    return $this;
  }

  public function getDocumentationLink() {
    return $this->documentationLink;
  }

  public function appendList(array $items) {
    foreach ($items as $key => $item) {
      $items[$key] = phutil_tag(
        'li',
        array(
          'class' => 'remarkup-list-item',
        ),
        $item);
    }

    $list = phutil_tag(
      'ul',
      array(
        'class' => 'remarkup-list',
      ),
      $items);

    return $this->appendChild($list);
  }

  public function appendHint($content) {
    $hint = phutil_tag(
      'p',
      array(
        'class' => 'phui-policy-section-view-hint',
      ),
      array(
        id(new PHUIIconView())
          ->setIcon('fa-sticky-note bluegrey'),
        ' ',
        pht('Note:'),
        ' ',
        $content,
      ));

    return $this->appendChild($hint);
  }

  public function appendParagraph($content) {
    return $this->appendChild(phutil_tag('p', array(), $content));
  }

  public function appendRulesView(PhabricatorPolicyRulesView $rules_view) {
    return $this->appendChild(
      phutil_tag(
        'div',
        array(
          'class' => 'phui-policy-section-view-rules',
        ),
        $rules_view));
  }

  protected function getTagAttributes() {
    return array(
      'class' => 'phui-policy-section-view',
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phui-policy-section-view-css');

    $icon_view = null;
    $icon = $this->getIcon();
    if ($icon !== null) {
      $icon_view = id(new PHUIIconView())
        ->setIcon($icon);
    }

    $header_view = phutil_tag(
      'span',
      array(
        'class' => 'phui-policy-section-view-header-text',
      ),
      $this->getHeader());

    $header = phutil_tag(
      'div',
      array(
        'class' => 'phui-policy-section-view-header',
      ),
      array(
        $icon_view,
        $header_view,
        $this->getDocumentationLink(),
      ));

    return array(
      $header,
      phutil_tag(
        'div',
        array(
          'class' => 'phui-policy-section-view-body',
        ),
        $this->renderChildren()),
    );
  }


}
