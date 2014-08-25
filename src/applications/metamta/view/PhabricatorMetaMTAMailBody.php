<?php

/**
 * Render the body of an application email by building it up section-by-section.
 *
 * @task compose  Composition
 * @task render   Rendering
 */
final class PhabricatorMetaMTAMailBody {

  private $sections = array();
  private $htmlSections = array();
  private $attachments = array();


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

  public function addPlaintextSection($header, $text) {
    $this->sections[] = $header."\n".$this->indent($text);
    return $this;
  }

  public function addHTMLSection($header, $html_fragment) {
    $this->htmlSections[] = array(
      phutil_tag('div', array('style' => 'font-weight:800;'), $header),
      $html_fragment);

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

    $this->addTextSection(
      pht('WHY DID I GET THIS EMAIL?'),
      PhabricatorEnv::getProductionURI($xscript_uri));

    return $this;
  }


  /**
   * Add a section with reply handler instructions.
   *
   * @param string Reply handler instructions.
   * @return this
   * @task compose
   */
  public function addReplySection($instructions) {
    if (!PhabricatorEnv::getEnvConfig('metamta.reply.show-hints')) {
      return $this;
    }
    if (!strlen($instructions)) {
      return $this;
    }

    $this->addTextSection(pht('REPLY HANDLER ACTIONS'), $instructions);

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
