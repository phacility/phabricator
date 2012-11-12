<?php

final class DifferentialChangesetListView
  extends DifferentialCodeWidthSensitiveView {

  private $changesets = array();
  private $visibleChangesets = array();
  private $references = array();
  private $inlineURI;
  private $renderURI = '/differential/changeset/';
  private $whitespace;

  private $standaloneURI;
  private $leftRawFileURI;
  private $rightRawFileURI;

  private $user;
  private $symbolIndexes = array();
  private $repository;
  private $branch;
  private $diff;
  private $vsMap = array();

  public function setBranch($branch) {
    $this->branch = $branch;
    return $this;
  }
  private function getBranch() {
    return $this->branch;
  }

  public function setChangesets($changesets) {
    $this->changesets = $changesets;
    return $this;
  }

  public function setVisibleChangesets($visible_changesets) {
    $this->visibleChangesets = $visible_changesets;
    return $this;
  }

  public function setInlineCommentControllerURI($uri) {
    $this->inlineURI = $uri;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function setRenderingReferences(array $references) {
    $this->references = $references;
    return $this;
  }

  public function setSymbolIndexes(array $indexes) {
    $this->symbolIndexes = $indexes;
    return $this;
  }

  public function setRenderURI($render_uri) {
    $this->renderURI = $render_uri;
    return $this;
  }

  public function setWhitespace($whitespace) {
    $this->whitespace = $whitespace;
    return $this;
  }

  public function setVsMap(array $vs_map) {
    $this->vsMap = $vs_map;
    return $this;
  }

  public function getVsMap() {
    return $this->vsMap;
  }

  public function setStandaloneURI($uri) {
    $this->standaloneURI = $uri;
    return $this;
  }

  public function setRawFileURIs($l, $r) {
    $this->leftRawFileURI = $l;
    $this->rightRawFileURI = $r;
    return $this;
  }

  public function render() {
    require_celerity_resource('differential-changeset-view-css');

    $changesets = $this->changesets;

    Javelin::initBehavior('differential-toggle-files', array());

    $output = array();
    $mapping = array();
    foreach ($changesets as $key => $changeset) {
      $file = $changeset->getFilename();
      $class = 'differential-changeset';
      if (!$this->inlineURI) {
        $class .= ' differential-changeset-noneditable';
      }

      $ref = $this->references[$key];

      $detail = new DifferentialChangesetDetailView();

      $view_options = $this->renderViewOptionsDropdown(
        $detail,
        $ref,
        $changeset);

      $prefs = $this->user->loadPreferences();
      $pref_symbols = $prefs->getPreference(
        PhabricatorUserPreferences::PREFERENCE_DIFFUSION_SYMBOLS);
      $detail->setChangeset($changeset);
      $detail->addButton($view_options);
      if ($pref_symbols != 'disabled') {
        $detail->setSymbolIndex(idx($this->symbolIndexes, $key));
      }
      $detail->setVsChangesetID(idx($this->vsMap, $changeset->getID()));
      $detail->setEditable(true);

      $uniq_id = 'diff-'.$changeset->getAnchorName();
      if (isset($this->visibleChangesets[$key])) {
        $load = 'Loading...';
        $mapping[$uniq_id] = $ref;
      } else {
        $load = javelin_render_tag(
          'a',
          array(
            'href' => '#'.$uniq_id,
            'meta' => array(
              'id' => $uniq_id,
              'ref' => $ref,
              'kill' => true,
            ),
            'sigil' => 'differential-load',
            'mustcapture' => true,
          ),
          'Load');
      }
      $detail->appendChild(
        phutil_render_tag(
          'div',
          array(
            'id' => $uniq_id,
          ),
          '<div class="differential-loading">'.$load.'</div>'));
      $output[] = $detail->render();
    }

    require_celerity_resource('aphront-tooltip-css');

    Javelin::initBehavior('differential-populate', array(
      'registry'    => $mapping,
      'whitespace'  => $this->whitespace,
      'uri'         => $this->renderURI,
    ));

    Javelin::initBehavior('differential-show-more', array(
      'uri' => $this->renderURI,
      'whitespace' => $this->whitespace,
    ));

    Javelin::initBehavior('differential-comment-jump', array());

    if ($this->inlineURI) {
      $undo_templates = $this->renderUndoTemplates();

      Javelin::initBehavior('differential-edit-inline-comments', array(
        'uri'             => $this->inlineURI,
        'undo_templates'  => $undo_templates,
        'stage'           => 'differential-review-stage',
      ));
    }

    return phutil_render_tag(
      'div',
      array(
        'class' => 'differential-review-stage',
        'id'    => 'differential-review-stage',
        'style' => "max-width: {$this->calculateSideBySideWidth()}px; ",
      ),
      implode("\n", $output));
  }

  /**
   * Render the "Undo" markup for the inline comment undo feature.
   */
  private function renderUndoTemplates() {
    $link = javelin_render_tag(
      'a',
      array(
        'href'  => '#',
        'sigil' => 'differential-inline-comment-undo',
      ),
      'Undo');

    $div = phutil_render_tag(
      'div',
      array(
        'class' => 'differential-inline-undo',
      ),
      'Changes discarded. '.$link);

    $template =
      '<table><tr>'.
      '<th></th><td>%s</td>'.
      '<th></th><td colspan="2">%s</td>'.
      '</tr></table>';

    return array(
      'l' => sprintf($template, $div, ''),
      'r' => sprintf($template, '', $div),
    );
  }

  private function renderViewOptionsDropdown(
    DifferentialChangesetDetailView $detail,
    $ref,
    DifferentialChangeset $changeset) {

    $meta = array();

    $qparams = array(
      'ref'         => $ref,
      'whitespace'  => $this->whitespace,
    );

    if ($this->standaloneURI) {
      $uri = new PhutilURI($this->standaloneURI);
      $uri->setQueryParams($uri->getQueryParams() + $qparams);
      $meta['standaloneURI'] = (string)$uri;
    }

    $repository = $this->repository;
    if ($repository) {
      $meta['diffusionURI'] = (string)$repository->getDiffusionBrowseURIForPath(
        $changeset->getAbsoluteRepositoryPath($repository, $this->diff),
        idx($changeset->getMetadata(), 'line:first'),
        $this->getBranch());
    }

    $change = $changeset->getChangeType();

    if ($this->leftRawFileURI) {
      if ($change != DifferentialChangeType::TYPE_ADD) {
        $uri = new PhutilURI($this->leftRawFileURI);
        $uri->setQueryParams($uri->getQueryParams() + $qparams);
        $meta['leftURI'] = (string)$uri;
      }
    }

    if ($this->rightRawFileURI) {
      if ($change != DifferentialChangeType::TYPE_DELETE &&
          $change != DifferentialChangeType::TYPE_MULTICOPY) {
        $uri = new PhutilURI($this->rightRawFileURI);
        $uri->setQueryParams($uri->getQueryParams() + $qparams);
        $meta['rightURI'] = (string)$uri;
      }
    }

    $user = $this->user;
    if ($user && $repository) {
      $path = ltrim(
        $changeset->getAbsoluteRepositoryPath($repository, $this->diff),
        '/');
      $line = idx($changeset->getMetadata(), 'line:first', 1);
      $callsign = $repository->getCallsign();
      $editor_link = $user->loadEditorLink($path, $line, $callsign);
      if ($editor_link) {
        $meta['editor'] = $editor_link;
      } else {
        $meta['editorConfigure'] = '/settings/panel/display/';
      }
    }

    $meta['containerID'] = $detail->getID();

    Javelin::initBehavior(
      'differential-dropdown-menus',
      array());

    return javelin_render_tag(
      'a',
      array(
        'class'   => 'button small grey',
        'meta'    => $meta,
        'href'    => idx($meta, 'detailURI', '#'),
        'target'  => '_blank',
        'sigil'   => 'differential-view-options',
      ),
      "View Options \xE2\x96\xBC");
  }

}
