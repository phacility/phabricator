<?php

/**
 * Simple API for rendering blocks of Remarkup.
 *
 * Example usage:
 *
 *   $fancy_text = new PHUIRemarkupView($viewer, $raw_remarkup);
 *   $view->appendChild($fancy_text);
 *
 */
final class PHUIRemarkupView extends AphrontView {

  private $corpus;
  private $contextObject;
  private $options;
  private $oneoff;
  private $generateTableOfContents;

  // TODO: In the long run, rules themselves should define available options.
  // For now, just define constants here so we can more easily replace things
  // later once this is cleaned up.
  const OPTION_PRESERVE_LINEBREAKS = 'preserve-linebreaks';
  const OPTION_GENERATE_TOC = 'header.generate-toc';

  public function __construct(PhabricatorUser $viewer, $corpus) {
    $this->setUser($viewer);
    $this->corpus = $corpus;
  }

  public function setContextObject($context_object) {
    $this->contextObject = $context_object;
    return $this;
  }

  public function getContextObject() {
    return $this->contextObject;
  }

  public function setRemarkupOption($key, $value) {
    $this->options[$key] = $value;
    return $this;
  }

  public function setRemarkupOptions(array $options) {
    foreach ($options as $key => $value) {
      $this->setRemarkupOption($key, $value);
    }
    return $this;
  }

  public function setGenerateTableOfContents($generate) {
    $this->generateTableOfContents = $generate;
    return $this;
  }

  public function getGenerateTableOfContents() {
    return $this->generateTableOfContents;
  }

  public function getTableOfContents() {
    return $this->oneoff->getTableOfContents();
  }

  public function render() {
    $viewer = $this->getViewer();
    $corpus = $this->corpus;
    $context = $this->getContextObject();

    $options = $this->options;

    $oneoff = id(new PhabricatorMarkupOneOff())
      ->setContent($corpus)
      ->setContentCacheFragment($this->getContentCacheFragment());

    if ($options) {
      $oneoff->setEngine($this->getEngine());
    } else {
      $oneoff->setPreserveLinebreaks(true);
    }

    $generate_toc = $this->getGenerateTableOfContents();
    $oneoff->setGenerateTableOfContents($generate_toc);
    $this->oneoff = $oneoff;

    $content = PhabricatorMarkupEngine::renderOneObject(
      $oneoff,
      'default',
      $viewer,
      $context);

    return $content;
  }

  private function getEngine() {
    $options = $this->options;
    $viewer = $this->getViewer();

    $viewer_key = $viewer->getCacheFragment();
    $engine_key = $this->getEngineCacheFragment();

    $cache = PhabricatorCaches::getRequestCache();
    $cache_key = "remarkup.engine({$viewer_key}, {$engine_key})";

    $engine = $cache->getKey($cache_key);
    if (!$engine) {
      $engine = PhabricatorMarkupEngine::newMarkupEngine($options);
      $cache->setKey($cache_key, $engine);
    }

    return $engine;
  }

  private function getEngineCacheFragment() {
    $options = $this->options;

    ksort($options);

    $engine_key = serialize($options);
    $engine_key = PhabricatorHash::digestForIndex($engine_key);

    return $engine_key;
  }

  private function getContentCacheFragment() {
    $corpus = $this->corpus;

    $content_fragment = PhabricatorHash::digestForIndex($corpus);
    $options_fragment = array(
      'toc' => $this->getGenerateTableOfContents(),
    );
    $options_fragment = serialize($options_fragment);
    $options_fragment = PhabricatorHash::digestForIndex($options_fragment);

    return "remarkup({$content_fragment}, {$options_fragment})";
  }

}
