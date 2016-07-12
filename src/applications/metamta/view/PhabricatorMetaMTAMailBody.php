<?php

/**
 * Render the body of an application email by building it up section-by-section.
 *
 * @task compose  Composition
 * @task render   Rendering
 */
final class PhabricatorMetaMTAMailBody extends Phobject {

  private $sections = array();
  private $htmlSections = array();
  private $attachments = array();

  private $viewer;

  public function getViewer() {
    return $this->viewer;
  }

  public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

/* -(  Composition  )-------------------------------------------------------- */


  /**
   * Add a raw block of text to the email. This will be rendered as-is.
   *
   * @param string Block of text.
   * @return this
   * @task compose
   */
  public function addRawSection($text) {
    if (strlen($text)) {
      $text = rtrim($text);
      $this->sections[] = $text;
      $this->htmlSections[] = phutil_escape_html_newlines(
        phutil_tag('div', array(), $text));
    }
    return $this;
  }

  public function addRemarkupSection($header, $text) {
    try {
      $engine = PhabricatorMarkupEngine::newMarkupEngine(array());
      $engine->setConfig('viewer', $this->getViewer());
      $engine->setMode(PhutilRemarkupEngine::MODE_TEXT);
      $styled_text = $engine->markupText($text);
      $this->addPlaintextSection($header, $styled_text);
    } catch (Exception $ex) {
      phlog($ex);
      $this->addTextSection($header, $text);
    }

    try {
      $mail_engine = PhabricatorMarkupEngine::newMarkupEngine(array());
      $mail_engine->setConfig('viewer', $this->getViewer());
      $mail_engine->setMode(PhutilRemarkupEngine::MODE_HTML_MAIL);
      $mail_engine->setConfig(
        'uri.base',
        PhabricatorEnv::getProductionURI('/'));
      $html = $mail_engine->markupText($text);
      $this->addHTMLSection($header, $html);
    } catch (Exception $ex) {
      phlog($ex);
      $this->addHTMLSection($header, $text);
    }

    return $this;
  }

  public function addRawPlaintextSection($text) {
    if (strlen($text)) {
      $text = rtrim($text);
      $this->sections[] = $text;
    }
    return $this;
  }

  public function addRawHTMLSection($html) {
    $this->htmlSections[] = phutil_safe_html($html);
    return $this;
  }


  /**
   * Add a block of text with a section header. This is rendered like this:
   *
   *    HEADER
   *      Text is indented.
   *
   * @param string Header text.
   * @param string Section text.
   * @return this
   * @task compose
   */
  public function addTextSection($header, $section) {
    if ($section instanceof PhabricatorMetaMTAMailSection) {
      $plaintext = $section->getPlaintext();
      $html = $section->getHTML();
    } else {
      $plaintext = $section;
      $html = phutil_escape_html_newlines(phutil_tag('div', array(), $section));
    }

    $this->addPlaintextSection($header, $plaintext);
    $this->addHTMLSection($header, $html);
    return $this;
  }

  public function addPlaintextSection($header, $text, $indent = true) {
    if ($indent) {
      $text = $this->indent($text);
    }
    $this->sections[] = $header."\n".$text;
    return $this;
  }

  public function addHTMLSection($header, $html_fragment) {
    if ($header !== null) {
      $header = phutil_tag('strong', array(), $header);
    }

    $this->htmlSections[] = array(
      phutil_tag(
        'div',
        array(),
        array(
          $header,
          phutil_tag('div', array(), $html_fragment),
        )),
    );
    return $this;
  }

  public function addLinkSection($header, $link) {
    $html = phutil_tag('a', array('href' => $link), $link);
    $this->addPlaintextSection($header, $link);
    $this->addHTMLSection($header, $html);
    return $this;
  }

  /**
   * Add a Herald section with a rule management URI and a transcript URI.
   *
   * @param string URI to rule transcripts.
   * @return this
   * @task compose
   */
  public function addHeraldSection($xscript_uri) {
    if (!PhabricatorEnv::getEnvConfig('metamta.herald.show-hints')) {
      return $this;
    }

    $this->addLinkSection(
      pht('WHY DID I GET THIS EMAIL?'),
      PhabricatorEnv::getProductionURI($xscript_uri));

    return $this;
  }

  /**
   * Add an attachment.
   *
   * @param PhabricatorMetaMTAAttachment Attachment.
   * @return this
   * @task compose
   */
  public function addAttachment(PhabricatorMetaMTAAttachment $attachment) {
    $this->attachments[] = $attachment;
    return $this;
  }


/* -(  Rendering  )---------------------------------------------------------- */


  /**
   * Render the email body.
   *
   * @return string Rendered body.
   * @task render
   */
  public function render() {
    return implode("\n\n", $this->sections)."\n";
  }

  public function renderHTML() {
    $br = phutil_tag('br');
    $body = phutil_implode_html($br, $this->htmlSections);
    return (string)hsprintf('%s', array($body, $br));
  }

  /**
   * Retrieve attachments.
   *
   * @return list<PhabricatorMetaMTAAttachment> Attachments.
   * @task render
   */
  public function getAttachments() {
    return $this->attachments;
  }


  /**
   * Indent a block of text for rendering under a section heading.
   *
   * @param string Text to indent.
   * @return string Indented text.
   * @task render
   */
  private function indent($text) {
    return rtrim("  ".str_replace("\n", "\n  ", $text));
  }
}
