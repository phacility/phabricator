<?php

final class DifferentialChangesetDetailView extends AphrontView {

  private $changeset;
  private $buttons = array();
  private $editable;
  private $symbolIndex;
  private $id;
  private $vsChangesetID;
  private $renderURI;
  private $whitespace;
  private $renderingRef;
  private $autoload;
  private $loaded;
  private $renderer;

  public function setAutoload($autoload) {
    $this->autoload = $autoload;
    return $this;
  }

  public function getAutoload() {
    return $this->autoload;
  }

  public function setLoaded($loaded) {
    $this->loaded = $loaded;
    return $this;
  }

  public function getLoaded() {
    return $this->loaded;
  }

  public function setRenderingRef($rendering_ref) {
    $this->renderingRef = $rendering_ref;
    return $this;
  }

  public function getRenderingRef() {
    return $this->renderingRef;
  }

  public function setWhitespace($whitespace) {
    $this->whitespace = $whitespace;
    return $this;
  }

  public function getWhitespace() {
    return $this->whitespace;
  }

  public function setRenderURI($render_uri) {
    $this->renderURI = $render_uri;
    return $this;
  }

  public function getRenderURI() {
    return $this->renderURI;
  }

  public function setChangeset($changeset) {
    $this->changeset = $changeset;
    return $this;
  }

  public function addButton($button) {
    $this->buttons[] = $button;
    return $this;
  }

  public function setEditable($editable) {
    $this->editable = $editable;
    return $this;
  }

  public function setSymbolIndex($symbol_index) {
    $this->symbolIndex = $symbol_index;
    return $this;
  }

  public function setRenderer($renderer) {
    $this->renderer = $renderer;
    return $this;
  }

  public function getRenderer() {
    return $this->renderer;
  }

  public function getID() {
    if (!$this->id) {
      $this->id = celerity_generate_unique_node_id();
    }
    return $this->id;
  }

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function setVsChangesetID($vs_changeset_id) {
    $this->vsChangesetID = $vs_changeset_id;
    return $this;
  }

  public function getVsChangesetID() {
    return $this->vsChangesetID;
  }

  public function render() {
    $this->requireResource('differential-changeset-view-css');
    $this->requireResource('syntax-highlighting-css');

    Javelin::initBehavior('phabricator-oncopy', array());

    $changeset = $this->changeset;
    $class = 'differential-changeset';
    if (!$this->editable) {
      $class .= ' differential-changeset-immutable';
    }

    $buttons = null;
    if ($this->buttons) {
      $buttons = phutil_tag(
        'div',
        array(
          'class' => 'differential-changeset-buttons',
        ),
        $this->buttons);
    }

    $id = $this->getID();

    if ($this->symbolIndex) {
      Javelin::initBehavior(
        'repository-crossreference',
        array(
          'container' => $id,
        ) + $this->symbolIndex);
    }

    $display_filename = $changeset->getDisplayFilename();
    $display_icon = FileTypeIcon::getFileIcon($display_filename);
    $icon = id(new PHUIIconView())
      ->setIcon($display_icon);

    $renderer = DifferentialChangesetHTMLRenderer::getHTMLRendererByKey(
      $this->getRenderer());

    return javelin_tag(
      'div',
      array(
        'sigil' => 'differential-changeset',
        'meta'  => array(
          'left'  => nonempty(
            $this->getVsChangesetID(),
            $this->changeset->getID()),
          'right' => $this->changeset->getID(),
          'renderURI' => $this->getRenderURI(),
          'whitespace' => $this->getWhitespace(),
          'highlight' => null,
          'renderer' => $this->getRenderer(),
          'ref' => $this->getRenderingRef(),
          'autoload' => $this->getAutoload(),
          'loaded' => $this->getLoaded(),
          'undoTemplates' => $renderer->renderUndoTemplates(),
        ),
        'class' => $class,
        'id'    => $id,
      ),
      array(
        id(new PhabricatorAnchorView())
          ->setAnchorName($changeset->getAnchorName())
          ->setNavigationMarker(true)
          ->render(),
        $buttons,
        phutil_tag('h1',
          array(
            'class' => 'differential-file-icon-header',
          ),
          array(
            $icon,
            $display_filename,
          )),
        javelin_tag(
          'div',
          array(
            'class' => 'changeset-view-content',
            'sigil' => 'changeset-view-content',
          ),
          $this->renderChildren()),
      ));
  }


}
