<?php

final class PhabricatorChangesetViewState
  extends Phobject {

  private $highlightLanguage;
  private $characterEncoding;
  private $documentEngineKey;
  private $rendererKey;
  private $defaultDeviceRendererKey;

  public function setHighlightLanguage($highlight_language) {
    $this->highlightLanguage = $highlight_language;
    return $this;
  }

  public function getHighlightLanguage() {
    return $this->highlightLanguage;
  }

  public function setCharacterEncoding($character_encoding) {
    $this->characterEncoding = $character_encoding;
    return $this;
  }

  public function getCharacterEncoding() {
    return $this->characterEncoding;
  }

  public function setDocumentEngineKey($document_engine_key) {
    $this->documentEngineKey = $document_engine_key;
    return $this;
  }

  public function getDocumentEngineKey() {
    return $this->documentEngineKey;
  }

  public function setRendererKey($renderer_key) {
    $this->rendererKey = $renderer_key;
    return $this;
  }

  public function getRendererKey() {
    return $this->rendererKey;
  }

  public function setDefaultDeviceRendererKey($renderer_key) {
    $this->defaultDeviceRendererKey = $renderer_key;
    return $this;
  }

  public function getDefaultDeviceRendererKey() {
    return $this->defaultDeviceRendererKey;
  }

}
