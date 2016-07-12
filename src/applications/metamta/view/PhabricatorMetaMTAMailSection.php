<?php

/**
 * Helper for building a rendered section.
 *
 * @task compose  Composition
 * @task render   Rendering
 * @group metamta
 */
final class PhabricatorMetaMTAMailSection extends Phobject {
  private $plaintextFragments = array();
  private $htmlFragments = array();

  public function getHTML() {
    return $this->htmlFragments;
  }

  public function getPlaintext() {
    return implode("\n", $this->plaintextFragments);
  }

  public function addHTMLFragment($fragment) {
    $this->htmlFragments[] = $fragment;
    return $this;
  }

  public function addPlaintextFragment($fragment) {
    $this->plaintextFragments[] = $fragment;
    return $this;
  }

  public function addFragment($fragment) {
    $this->plaintextFragments[] = $fragment;
    $this->htmlFragments[] =
      phutil_escape_html_newlines(phutil_tag('div', array(), $fragment));

    return $this;
  }
}
