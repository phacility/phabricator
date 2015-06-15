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
final class PhabricatorMarkupOneOff
  extends Phobject
  implements PhabricatorMarkupInterface {

  private $content;
  private $preserveLinebreaks;
  private $engineRuleset;
  private $disableCache;

  public function setEngineRuleset($engine_ruleset) {
    $this->engineRuleset = $engine_ruleset;
    return $this;
  }

  public function getEngineRuleset() {
    return $this->engineRuleset;
  }

  public function setPreserveLinebreaks($preserve_linebreaks) {
    $this->preserveLinebreaks = $preserve_linebreaks;
    return $this;
  }

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function getContent() {
    return $this->content;
  }

  public function setDisableCache($disable_cache) {
    $this->disableCache = $disable_cache;
    return $this;
  }

  public function getDisableCache() {
    return $this->disableCache;
  }

  public function getMarkupFieldKey($field) {
    return PhabricatorHash::digestForIndex($this->getContent()).':oneoff';
  }

  public function newMarkupEngine($field) {
    if ($this->engineRuleset) {
      return PhabricatorMarkupEngine::getEngine($this->engineRuleset);
    } else if ($this->preserveLinebreaks) {
      return PhabricatorMarkupEngine::getEngine();
    } else {
      return PhabricatorMarkupEngine::getEngine('nolinebreaks');
    }
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
    if ($this->getDisableCache()) {
      return false;
    }

    return true;
  }

}
