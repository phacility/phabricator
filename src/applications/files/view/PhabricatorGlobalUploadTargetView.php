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

  public function setShowIfSupportedID($show_if_supported_id) {
    $this->showIfSupportedID = $show_if_supported_id;
    return $this;
  }

  public function getShowIfSupportedID() {
    return $this->showIfSupportedID;
  }

  public function render() {
    $viewer = $this->getUser();
    if (!$viewer->isLoggedIn()) {
      return null;
    }

    $instructions_id = 'phabricator-global-drag-and-drop-upload-instructions';

    require_celerity_resource('global-drag-and-drop-css');

    // Use the configured default view policy. Drag and drop uploads use
    // a more restrictive view policy if we don't specify a policy explicitly,
    // as the more restrictive policy is correct for most drop targets (like
    // Pholio uploads and Remarkup text areas).

    $view_policy = PhabricatorFile::initializeNewFile()->getViewPolicy();

    Javelin::initBehavior('global-drag-and-drop', array(
      'ifSupported' => $this->showIfSupportedID,
      'instructions' => $instructions_id,
      'uploadURI' => '/file/dropupload/',
      'browseURI' => '/file/query/authored/',
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
      "\xE2\x87\xAA ".pht('Drop Files to Upload'));
  }
}
