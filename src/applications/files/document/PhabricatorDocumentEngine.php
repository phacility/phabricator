<?php

abstract class PhabricatorDocumentEngine
  extends Phobject {

  private $viewer;
  private $highlightedLines = array();
  private $encodingConfiguration;
  private $highlightingConfiguration;
  private $blameConfiguration = true;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setHighlightedLines(array $highlighted_lines) {
    $this->highlightedLines = $highlighted_lines;
    return $this;
  }

  final public function getHighlightedLines() {
    return $this->highlightedLines;
  }

  final public function canRenderDocument(PhabricatorDocumentRef $ref) {
    return $this->canRenderDocumentType($ref);
  }

  public function canDiffDocuments(
    PhabricatorDocumentRef $uref = null,
    PhabricatorDocumentRef $vref = null) {
    return false;
  }

  public function newBlockDiffViews(
    PhabricatorDocumentRef $uref,
    PhabricatorDocumentEngineBlock $ublock,
    PhabricatorDocumentRef $vref,
    PhabricatorDocumentEngineBlock $vblock) {

    $u_content = $this->newBlockContentView($uref, $ublock);
    $v_content = $this->newBlockContentView($vref, $vblock);

    return id(new PhabricatorDocumentEngineBlockDiff())
      ->setOldContent($u_content)
      ->addOldClass('old')
      ->addOldClass('old-full')
      ->setNewContent($v_content)
      ->addNewClass('new')
      ->addNewClass('new-full');
  }

  public function newBlockContentView(
    PhabricatorDocumentRef $ref,
    PhabricatorDocumentEngineBlock $block) {
    return $block->getContent();
  }

  public function newEngineBlocks(
    PhabricatorDocumentRef $uref,
    PhabricatorDocumentRef $vref) {
    throw new PhutilMethodNotImplementedException();
  }

  public function canConfigureEncoding(PhabricatorDocumentRef $ref) {
    return false;
  }

  public function canConfigureHighlighting(PhabricatorDocumentRef $ref) {
    return false;
  }

  public function canBlame(PhabricatorDocumentRef $ref) {
    return false;
  }

  final public function setEncodingConfiguration($config) {
    $this->encodingConfiguration = $config;
    return $this;
  }

  final public function getEncodingConfiguration() {
    return $this->encodingConfiguration;
  }

  final public function setHighlightingConfiguration($config) {
    $this->highlightingConfiguration = $config;
    return $this;
  }

  final public function getHighlightingConfiguration() {
    return $this->highlightingConfiguration;
  }

  final public function setBlameConfiguration($blame_configuration) {
    $this->blameConfiguration = $blame_configuration;
    return $this;
  }

  final public function getBlameConfiguration() {
    return $this->blameConfiguration;
  }

  final protected function getBlameEnabled() {
    return $this->blameConfiguration;
  }

  public function shouldRenderAsync(PhabricatorDocumentRef $ref) {
    return false;
  }

  abstract protected function canRenderDocumentType(
    PhabricatorDocumentRef $ref);

  final public function newDocument(PhabricatorDocumentRef $ref) {
    $can_complete = $this->canRenderCompleteDocument($ref);
    $can_partial = $this->canRenderPartialDocument($ref);

    if (!$can_complete && !$can_partial) {
      return $this->newMessage(
        pht(
          'This document is too large to be rendered inline. (The document '.
          'is %s bytes, the limit for this engine is %s bytes.)',
          new PhutilNumber($ref->getByteLength()),
          new PhutilNumber($this->getByteLengthLimit())));
    }

    return $this->newDocumentContent($ref);
  }

  final public function newDocumentIcon(PhabricatorDocumentRef $ref) {
    return id(new PHUIIconView())
      ->setIcon($this->getDocumentIconIcon($ref));
  }

  abstract protected function newDocumentContent(
    PhabricatorDocumentRef $ref);

  protected function getDocumentIconIcon(PhabricatorDocumentRef $ref) {
    return 'fa-file-o';
  }

  protected function getDocumentRenderingText(PhabricatorDocumentRef $ref) {
    return pht('Loading...');
  }

  final public function getDocumentEngineKey() {
    return $this->getPhobjectClassConstant('ENGINEKEY');
  }

  final public static function getAllEngines() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getDocumentEngineKey')
      ->execute();
  }

  final public function newSortVector(PhabricatorDocumentRef $ref) {
    $content_score = $this->getContentScore($ref);

    // Prefer engines which can render the entire file over engines which
    // can only render a header, and engines which can render a header over
    // engines which can't render anything.
    if ($this->canRenderCompleteDocument($ref)) {
      $limit_score = 0;
    } else if ($this->canRenderPartialDocument($ref)) {
      $limit_score = 1;
    } else {
      $limit_score = 2;
    }

    return id(new PhutilSortVector())
      ->addInt($limit_score)
      ->addInt(-$content_score);
  }

  protected function getContentScore(PhabricatorDocumentRef $ref) {
    return 2000;
  }

  abstract public function getViewAsLabel(PhabricatorDocumentRef $ref);

  public function getViewAsIconIcon(PhabricatorDocumentRef $ref) {
    $can_complete = $this->canRenderCompleteDocument($ref);
    $can_partial = $this->canRenderPartialDocument($ref);

    if (!$can_complete && !$can_partial) {
      return 'fa-times';
    }

    return $this->getDocumentIconIcon($ref);
  }

  public function getViewAsIconColor(PhabricatorDocumentRef $ref) {
    $can_complete = $this->canRenderCompleteDocument($ref);

    if (!$can_complete) {
      return 'grey';
    }

    return null;
  }

  final public static function getEnginesForRef(
    PhabricatorUser $viewer,
    PhabricatorDocumentRef $ref) {
    $engines = self::getAllEngines();

    foreach ($engines as $key => $engine) {
      $engine = id(clone $engine)
        ->setViewer($viewer);

      if (!$engine->canRenderDocument($ref)) {
        unset($engines[$key]);
        continue;
      }

      $engines[$key] = $engine;
    }

    if (!$engines) {
      throw new Exception(pht('No content engine can render this document.'));
    }

    $vectors = array();
    foreach ($engines as $key => $usable_engine) {
      $vectors[$key] = $usable_engine->newSortVector($ref);
    }
    $vectors = msortv($vectors, 'getSelf');

    return array_select_keys($engines, array_keys($vectors));
  }

  protected function getByteLengthLimit() {
    return (1024 * 1024 * 8);
  }

  protected function canRenderCompleteDocument(PhabricatorDocumentRef $ref) {
    $limit = $this->getByteLengthLimit();
    if ($limit) {
      $length = $ref->getByteLength();
      if ($length > $limit) {
        return false;
      }
    }

    return true;
  }

  protected function canRenderPartialDocument(PhabricatorDocumentRef $ref) {
    return false;
  }

  protected function newMessage($message) {
    return phutil_tag(
      'div',
      array(
        'class' => 'document-engine-error',
      ),
      $message);
  }

  final public function newLoadingContent(PhabricatorDocumentRef $ref) {
    $spinner = id(new PHUIIconView())
      ->setIcon('fa-gear')
      ->addClass('ph-spin');

    return phutil_tag(
      'div',
      array(
        'class' => 'document-engine-loading',
      ),
      array(
        $spinner,
        $this->getDocumentRenderingText($ref),
      ));
  }

  public function shouldSuggestEngine(PhabricatorDocumentRef $ref) {
    return false;
  }

}
