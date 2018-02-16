<?php

/**
 * DEPRECATED. Use @{class:PHUIRemarkupView}.
 */
final class PhabricatorMarkupOneOff
  extends Phobject
  implements PhabricatorMarkupInterface {

  private $content;
  private $preserveLinebreaks;
  private $engineRuleset;
  private $engine;
  private $disableCache;
  private $contentCacheFragment;

  private $generateTableOfContents;
  private $tableOfContents;

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

  public function setEngine(PhutilMarkupEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  public function getEngine() {
    return $this->engine;
  }

  public function setDisableCache($disable_cache) {
    $this->disableCache = $disable_cache;
    return $this;
  }

  public function getDisableCache() {
    return $this->disableCache;
  }

  public function setGenerateTableOfContents($generate) {
    $this->generateTableOfContents = $generate;
    return $this;
  }

  public function getGenerateTableOfContents() {
    return $this->generateTableOfContents;
  }

  public function getTableOfContents() {
    return $this->tableOfContents;
  }

  public function setContentCacheFragment($fragment) {
    $this->contentCacheFragment = $fragment;
    return $this;
  }

  public function getContentCacheFragment() {
    return $this->contentCacheFragment;
  }

  public function getMarkupFieldKey($field) {
    $fragment = $this->getContentCacheFragment();
    if ($fragment !== null) {
      return $fragment;
    }

    return PhabricatorHash::digestForIndex($this->getContent()).':oneoff';
  }

  public function newMarkupEngine($field) {
    if ($this->engine) {
      return $this->engine;
    }

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

    if ($this->getGenerateTableOfContents()) {
      $toc = PhutilRemarkupHeaderBlockRule::renderTableOfContents($engine);
      $this->tableOfContents = $toc;
    }

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
