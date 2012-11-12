<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleImageMacro
  extends PhutilRemarkupRule {

  private $images;

  public function apply($text) {
    return preg_replace_callback(
      '@^([a-zA-Z0-9_\-]+)$@m',
      array($this, 'markupImageMacro'),
      $text);
  }

  public function markupImageMacro($matches) {
    if ($this->images === null) {
      $this->images = array();
      $rows = id(new PhabricatorFileImageMacro())->loadAll();
      foreach ($rows as $row) {
        $this->images[$row->getName()] = $row->getFilePHID();
      }
    }

    if (array_key_exists($matches[1], $this->images)) {
      $phid = $this->images[$matches[1]];

      $file = id(new PhabricatorFile())->loadOneWhere('phid = %s', $phid);
      if ($file) {
        $src_uri = $file->getBestURI();
      } else {
        $src_uri = null;
      }

      $img = phutil_render_tag(
        'img',
        array(
          'src'   => $src_uri,
          'alt'   => $matches[1],
          'title' => $matches[1]),
        null);
      return $this->getEngine()->storeText($img);
    } else {
      return $matches[1];
    }
  }

}
