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
    $instructions_id = celerity_generate_unique_node_id();

    require_celerity_resource('global-drag-and-drop-css');

    Javelin::initBehavior('global-drag-and-drop', array(
      'ifSupported'   => $this->showIfSupportedID,
      'instructions'  => $instructions_id,
      'uploadURI'     => '/file/dropupload/',
      'browseURI'     => '/file/filter/my/',
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
