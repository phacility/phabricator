<?php

/**
 * IMPORTANT: If you use this, make sure to implement
 *
 *   public function isGlobalDragAndDropUploadEnabled() {
 *     return true;
 *   }
 *
 * on the controller(s) that render this class...! This is necessary
 * to make sure Quicksand works properly with the javascript in this
 * UI.
 */
final class PhabricatorGlobalUploadTargetView extends AphrontView {

  private $showIfSupportedID;
  private $hintText;
  private $viewPolicy;
  private $submitURI;

  public function setShowIfSupportedID($show_if_supported_id) {
    $this->showIfSupportedID = $show_if_supported_id;
    return $this;
  }

  public function getShowIfSupportedID() {
    return $this->showIfSupportedID;
  }

  public function setHintText($hint_text) {
    $this->hintText = $hint_text;
    return $this;
  }

  public function getHintText() {
    return $this->hintText;
  }

  public function setViewPolicy($view_policy) {
    $this->viewPolicy = $view_policy;
    return $this;
  }

  public function getViewPolicy() {
    return $this->viewPolicy;
  }

  public function setSubmitURI($submit_uri) {
    $this->submitURI = $submit_uri;
    return $this;
  }

  public function getSubmitURI() {
    return $this->submitURI;
  }



  public function render() {
    $viewer = $this->getViewer();
    if (!$viewer->isLoggedIn()) {
      return null;
    }

    $instructions_id = 'phabricator-global-drag-and-drop-upload-instructions';

    require_celerity_resource('global-drag-and-drop-css');

    $hint_text = $this->getHintText();
    if ($hint_text === null || !strlen($hint_text)) {
      $hint_text = "\xE2\x87\xAA ".pht('Drop Files to Upload');
    }

    // Use the configured default view policy. Drag and drop uploads use
    // a more restrictive view policy if we don't specify a policy explicitly,
    // as the more restrictive policy is correct for most drop targets (like
    // Pholio uploads and Remarkup text areas).

    $view_policy = $this->getViewPolicy();
    if ($view_policy === null) {
      $view_policy = PhabricatorFile::initializeNewFile()->getViewPolicy();
    }

    $submit_uri = $this->getSubmitURI();
    $done_uri = '/file/query/authored/';

    Javelin::initBehavior('global-drag-and-drop', array(
      'ifSupported' => $this->showIfSupportedID,
      'instructions' => $instructions_id,
      'uploadURI' => '/file/dropupload/',
      'submitURI' => $submit_uri,
      'browseURI' => $done_uri,
      'viewPolicy' => $view_policy,
      'chunkThreshold' => PhabricatorFileStorageEngine::getChunkThreshold(),
    ));

    return phutil_tag(
      'div',
      array(
        'id'    => $instructions_id,
        'class' => 'phabricator-global-upload-instructions',
        'style' => 'display: none;',
      ),
      $hint_text);
  }
}
