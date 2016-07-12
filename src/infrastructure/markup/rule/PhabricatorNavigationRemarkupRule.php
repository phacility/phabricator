<?php

final class PhabricatorNavigationRemarkupRule extends PhutilRemarkupRule {

  public function getPriority() {
    return 200.0;
  }

  public function apply($text) {
    return preg_replace_callback(
      '@{nav\b((?:[^}\\\\]+|\\\\.)*)}@m',
      array($this, 'markupNavigation'),
      $text);
  }

  public function markupNavigation(array $matches) {
    if (!$this->isFlatText($matches[0])) {
      return $matches[0];
    }

    $elements = ltrim($matches[1], ", \n");
    $elements = explode('>', $elements);

    $defaults = array(
      'name' => null,
      'type' => 'link',
      'href' => null,
      'icon' => null,
    );

    $sequence = array();
    $parser = new PhutilSimpleOptions();
    foreach ($elements as $element) {
      if (strpos($element, '=') === false) {
        $sequence[] = array(
          'name' => trim($element),
        ) + $defaults;
      } else {
        $sequence[] = $parser->parse($element) + $defaults;
      }
    }

    if ($this->getEngine()->isTextMode()) {
      return implode(' > ', ipull($sequence, 'name'));
    }

    static $icon_names;
    if (!$icon_names) {
      $icon_names = array_fuse(PHUIIconView::getIcons());
    }

    $out = array();
    foreach ($sequence as $item) {
      $item_name = $item['name'];
      $item_color = PHUITagView::COLOR_GREY;
      if ($item['type'] == 'instructions') {
        $item_name = phutil_tag('em', array(), $item_name);
        $item_color = PHUITagView::COLOR_INDIGO;
      }

      $tag = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_SHADE)
        ->setShade($item_color)
        ->setName($item_name);

      if ($item['icon']) {
        $icon_name = 'fa-'.$item['icon'];
        if (isset($icon_names[$icon_name])) {
          $tag->setIcon($icon_name);
        }
      }

      if ($item['href'] !== null) {
        if (PhabricatorEnv::isValidRemoteURIForLink($item['href'])) {
          $tag->setHref($item['href']);
          $tag->setExternal(true);
        }
      }

      $out[] = $tag;
    }

    if ($this->getEngine()->isHTMLMailMode()) {
      $arrow_attr = array(
        'style' => 'color: #92969D;',
      );
      $nav_attr = array();
    } else {
      $arrow_attr = array(
        'class' => 'remarkup-nav-sequence-arrow',
      );
      $nav_attr = array(
        'class' => 'remarkup-nav-sequence',
      );
    }

    $joiner = phutil_tag(
      'span',
      $arrow_attr,
      " \xE2\x86\x92 ");

    $out = phutil_implode_html($joiner, $out);

    $out = phutil_tag(
      'span',
      $nav_attr,
      $out);

    return $this->getEngine()->storeText($out);
  }

}
