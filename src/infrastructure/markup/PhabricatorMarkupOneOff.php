<?php

/**
 * Concrete object for accessing the markup engine with arbitrary blobs of
 * text, like form instructions. Usage:
 *
 *   $output = PhabricatorMarkupEngine::renderOneObject(
 *     id(new PhabricatorMarkupOneOff())->setContent($some_content),
 *     'default',
 *     $viewer);
 *
 * This is less efficient than batching rendering, but appropriate for small
 * amounts of one-off text in form instructions.
 */
final class PhabricatorMarkupOneOff implements PhabricatorMarkupInterface {

  private $content;

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function getContent() {
    return $this->content;
  }

  public function getMarkupFieldKey($field) {
    return PhabricatorHash::digestForIndex($this->getContent()).':oneoff';
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newMarkupEngine(
      array(
        'preserve-linebreaks' => false,
      ));
  }

  public function getMarkupText($field) {
    return $this->getContent();
  }

  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {

    require_celerity_resource('phabricator-remarkup-css');
    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $output);
  }

  public function shouldUseMarkupCache($field) {
    return true;
  }

}
