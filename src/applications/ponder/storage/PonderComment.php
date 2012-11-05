<?php

final class PonderComment extends PonderDAO
  implements PhabricatorMarkupInterface {

  const MARKUP_FIELD_CONTENT = 'markup:content';

  protected $targetPHID;
  protected $authorPHID;
  protected $content;

  public function getMarkupFieldKey($field) {
    $hash = PhabricatorHash::digest($this->getMarkupText($field));
    $id = $this->getID();
    return "ponder:c{$id}:{$field}:{$hash}";
  }

  public function getMarkupText($field) {
    return $this->getContent();
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newPonderMarkupEngine();
  }

  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    return (bool)$this->getID();
  }

  public function getMarkupField() {
    return self::MARKUP_FIELD_CONTENT;
  }
}

