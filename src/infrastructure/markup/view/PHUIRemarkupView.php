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

  // TODO: In the long run, rules themselves should define available options.
  // For now, just define constants here so we can more easily replace things
  // later once this is cleaned up.
  const OPTION_PRESERVE_LINEBREAKS = 'preserve-linebreaks';

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

  public function render() {
    $viewer = $this->getViewer();
    $corpus = $this->corpus;
    $context = $this->getContextObject();

    $options = $this->options;

    $oneoff = id(new PhabricatorMarkupOneOff())
      ->setContent($corpus);

    if ($options) {
      $oneoff->setEngine($this->getEngine());
    } else {
      $oneoff->setPreserveLinebreaks(true);
    }

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

    ksort($options);
    $engine_key = serialize($options);
    $engine_key = PhabricatorHash::digestForIndex($engine_key);

    $cache = PhabricatorCaches::getRequestCache();
    $cache_key = "remarkup.engine({$viewer_key}, {$engine_key})";

    $engine = $cache->getKey($cache_key);
    if (!$engine) {
      $engine = PhabricatorMarkupEngine::newMarkupEngine($options);
      $cache->setKey($cache_key, $engine);
    }

    return $engine;
  }

}
