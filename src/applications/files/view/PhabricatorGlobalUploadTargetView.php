<?php

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

    $instructions_id = celerity_generate_unique_node_id();

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
      pht("\xE2\x87\xAA Drop Files to Upload"));
  }
}
