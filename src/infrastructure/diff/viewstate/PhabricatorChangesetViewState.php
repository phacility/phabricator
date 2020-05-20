<?php

final class PhabricatorChangesetViewState
  extends Phobject {

  private $highlightLanguage;
  private $characterEncoding;
  private $documentEngineKey;
  private $rendererKey;
  private $defaultDeviceRendererKey;
  private $hidden;
  private $modifiedSinceHide;
  private $discardResponse;

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

  public function setHidden($hidden) {
    $this->hidden = $hidden;
    return $this;
  }

  public function getHidden() {
    return $this->hidden;
  }

  public function setModifiedSinceHide($modified_since_hide) {
    $this->modifiedSinceHide = $modified_since_hide;
    return $this;
  }

  public function getModifiedSinceHide() {
    return $this->modifiedSinceHide;
  }

  public function setDiscardResponse($discard_response) {
    $this->discardResponse = $discard_response;
    return $this;
  }

  public function getDiscardResponse() {
    return $this->discardResponse;
  }

}
