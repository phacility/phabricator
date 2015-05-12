<?php
final class CustomInlineImageRule extends PhabricatorRemarkupCustomInlineRule {

  public function apply($text) {
    return preg_replace_callback(
      '{<img.*?src="(.*?)".*?>}s',
      array($this, 'markupInlineCodeBlock'),
      $text);
  }

  public function getPriority() {
    return 200.0;
  }

  public function markupInlineCodeBlock($matches) {
    $engine = $this->getEngine();
    
    $text = $matches[1];
    $src = phutil_tag(
             'img',
             array(
               'src' => $text
             ), '');
    return $engine->storeText($src);
  }
}
?>
