<?php

final class DifferentialChangesetListView extends AphrontView {

  private $changesets = array();
  private $visibleChangesets = array();
  private $references = array();
  private $inlineURI;
  private $renderURI = '/differential/changeset/';
  private $background;
  private $header;
  private $isStandalone;

  private $standaloneURI;
  private $leftRawFileURI;
  private $rightRawFileURI;
  private $inlineListURI;

  private $symbolIndexes = array();
  private $repository;
  private $branch;
  private $diff;
  private $vsMap = array();

  private $title;
  private $parser;
  private $formationView;

  public function setParser(DifferentialChangesetParser $parser) {
    $this->parser = $parser;
    return $this;
  }

  public function getParser() {
    return $this->parser;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }
  private function getTitle() {
    return $this->title;
  }

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

  public function setInlineListURI($uri) {
    $this->inlineListURI = $uri;
    return $this;
  }

  public function getInlineListURI() {
    return $this->inlineListURI;
  }

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->repository;
  }

  public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function getDiff() {
    return $this->diff;
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

  public function setIsStandalone($is_standalone) {
    $this->isStandalone = $is_standalone;
    return $this;
  }

  public function getIsStandalone() {
    return $this->isStandalone;
  }

  public function setBackground($background) {
    $this->background = $background;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setFormationView(PHUIFormationView $formation_view) {
    $this->formationView = $formation_view;
    return $this;
  }

  public function getFormationView() {
    return $this->formationView;
  }

  public function render() {
    $viewer = $this->getViewer();

    $this->requireResource('differential-changeset-view-css');

    $changesets = $this->changesets;

    $repository = $this->getRepository();
    $diff = $this->getDiff();

    $output = array();
    $ids = array();
    foreach ($changesets as $key => $changeset) {

      $file = $changeset->getFilename();
      $ref = $this->references[$key];

      $detail = id(new DifferentialChangesetDetailView())
        ->setViewer($viewer);

      if ($repository) {
        $detail->setRepository($repository);
      }

      if ($diff) {
        $detail->setDiff($diff);
      }

      $uniq_id = 'diff-'.$changeset->getAnchorName();
      $detail->setID($uniq_id);

      $view_options = $this->renderViewOptionsDropdown(
        $detail,
        $ref,
        $changeset);

      $detail->setChangeset($changeset);
      $detail->addButton($view_options);
      $detail->setSymbolIndex(idx($this->symbolIndexes, $key));
      $detail->setVsChangesetID(idx($this->vsMap, $changeset->getID()));
      $detail->setEditable(true);
      $detail->setRenderingRef($ref);
      $detail->setBranch($this->getBranch());

      $detail->setRenderURI($this->renderURI);

      $parser = $this->getParser();
      if ($parser) {
        $response = $parser->newChangesetResponse();
        $detail->setChangesetResponse($response);
      } else {
        $detail->setAutoload(isset($this->visibleChangesets[$key]));
        if (isset($this->visibleChangesets[$key])) {
          $load = pht('Loading...');
        } else {
          $load = javelin_tag(
            'a',
            array(
              'class' => 'button button-grey',
              'href' => '#'.$uniq_id,
              'sigil' => 'differential-load',
              'meta' => array(
                'id' => $detail->getID(),
                'kill' => true,
              ),
              'mustcapture' => true,
            ),
            pht('Load File'));
        }
        $detail->appendChild(
          phutil_tag(
            'div',
            array(
              'id' => $uniq_id,
            ),
            phutil_tag(
              'div',
              array('class' => 'differential-loading'),
              $load)));
      }

      $output[] = $detail->render();
      $ids[] = $detail->getID();
    }

    $this->requireResource('aphront-tooltip-css');

    $formation_id = null;
    $formation_view = $this->getFormationView();
    if ($formation_view) {
      $formation_id = $formation_view->getID();
    }

    $this->initBehavior(
      'differential-populate',
      array(
      'changesetViewIDs' => $ids,
      'formationViewID' => $formation_id,
      'inlineURI' => $this->inlineURI,
      'inlineListURI' => $this->inlineListURI,
      'isStandalone' => $this->getIsStandalone(),
      'pht' => array(
        'Open in Editor' => pht('Open in Editor'),
        'Show All Context' => pht('Show All Context'),
        'All Context Shown' => pht('All Context Shown'),
        'Expand File' => pht('Expand File'),
        'Hide Changeset' => pht('Hide Changeset'),
        'Show Path in Repository' => pht('Show Path in Repository'),
        'Show Directory in Repository' => pht('Show Directory in Repository'),
        'View Standalone' => pht('View Standalone'),
        'Show Raw File (Left)' => pht('Show Raw File (Left)'),
        'Show Raw File (Right)' => pht('Show Raw File (Right)'),
        'Configure Editor' => pht('Configure Editor'),
        'Load Changes' => pht('Load Changes'),
        'View Side-by-Side Diff' => pht('View Side-by-Side Diff'),
        'View Unified Diff' => pht('View Unified Diff'),
        'Change Text Encoding...' => pht('Change Text Encoding...'),
        'Highlight As...' => pht('Highlight As...'),
        'View As Document Type...' => pht('View As Document Type...'),

        'Loading...' => pht('Loading...'),

        'Editing Comment' => pht('Editing Comment'),

        'Jump to next change.' => pht('Jump to next change.'),
        'Jump to previous change.' => pht('Jump to previous change.'),
        'Jump to next file.' => pht('Jump to next file.'),
        'Jump to previous file.' => pht('Jump to previous file.'),
        'Jump to next inline comment.' => pht('Jump to next inline comment.'),
        'Jump to previous inline comment.' =>
          pht('Jump to previous inline comment.'),
        'Jump to the table of contents.' =>
          pht('Jump to the table of contents.'),

        'Edit selected inline comment.' =>
          pht('Edit selected inline comment.'),
        'You must select a comment to edit.' =>
          pht('You must select a comment to edit.'),

        'Reply to selected inline comment or change.' =>
          pht('Reply to selected inline comment or change.'),
        'You must select a comment or change to reply to.' =>
          pht('You must select a comment or change to reply to.'),
        'Reply and quote selected inline comment.' =>
          pht('Reply and quote selected inline comment.'),

        'Mark or unmark selected inline comment as done.' =>
          pht('Mark or unmark selected inline comment as done.'),
        'You must select a comment to mark done.' =>
          pht('You must select a comment to mark done.'),

        'Collapse or expand inline comment.' =>
          pht('Collapse or expand inline comment.'),
        'You must select a comment to hide.' =>
          pht('You must select a comment to hide.'),

        'Jump to next inline comment, including collapsed comments.' =>
          pht('Jump to next inline comment, including collapsed comments.'),
        'Jump to previous inline comment, including collapsed comments.' =>
          pht('Jump to previous inline comment, including collapsed comments.'),

        'Hide or show the current changeset.' =>
          pht('Hide or show the current changeset.'),
        'You must select a file to hide or show.' =>
          pht('You must select a file to hide or show.'),

        'Unsaved' => pht('Unsaved'),
        'Unsubmitted' => pht('Unsubmitted'),
        'Comments' => pht('Comments'),

        'Hide "Done" Inlines' => pht('Hide "Done" Inlines'),
        'Hide Collapsed Inlines' => pht('Hide Collapsed Inlines'),
        'Hide Older Inlines' => pht('Hide Older Inlines'),
        'Hide All Inlines' => pht('Hide All Inlines'),
        'Show All Inlines' => pht('Show All Inlines'),

        'List Inline Comments' => pht('List Inline Comments'),
        'Display Options' => pht('Display Options'),

        'Hide or show all inline comments.' =>
          pht('Hide or show all inline comments.'),

        'Finish editing inline comments before changing display modes.' =>
          pht('Finish editing inline comments before changing display modes.'),

        'Open file in external editor.' =>
          pht('Open file in external editor.'),

        'You must select a file to edit.' =>
          pht('You must select a file to edit.'),

        'You must select a file to open.' =>
          pht('You must select a file to open.'),

        'No external editor is configured.' =>
          pht('No external editor is configured.'),

        'Hide or show the paths panel.' =>
          pht('Hide or show the paths panel.'),

        'Show path in repository.' =>
          pht('Show path in repository.'),
        'Show directory in repository.' =>
          pht('Show directory in repository.'),

        'Jump to the comment area.' =>
          pht('Jump to the comment area.'),

        'Show Changeset' => pht('Show Changeset'),

        'You must select source text to create a new inline comment.' =>
          pht('You must select source text to create a new inline comment.'),

        'New Inline Comment' => pht('New Inline Comment'),

        'Add new inline comment on selected source text.' =>
          pht('Add new inline comment on selected source text.'),

        'Suggest Edit' => pht('Suggest Edit'),
        'Discard Edit' => pht('Discard Edit'),
      ),
    ));

    if ($this->header) {
      $header = $this->header;
    } else {
      $header = id(new PHUIHeaderView())
        ->setHeader($this->getTitle());
    }

    $content = phutil_tag(
      'div',
      array(
        'class' => 'differential-review-stage',
        'id'    => 'differential-review-stage',
      ),
      $output);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground($this->background)
      ->setCollapsed(true)
      ->appendChild($content);

    return $object_box;
  }

  private function renderViewOptionsDropdown(
    DifferentialChangesetDetailView $detail,
    $ref,
    DifferentialChangeset $changeset) {
    $viewer = $this->getViewer();

    $meta = array();

    $qparams = array(
      'ref' => $ref,
    );

    if ($this->standaloneURI) {
      $uri = new PhutilURI($this->standaloneURI);
      $uri = $this->appendDefaultQueryParams($uri, $qparams);
      $meta['standaloneURI'] = (string)$uri;
    }

    $change = $changeset->getChangeType();

    if ($this->leftRawFileURI) {
      if ($change != DifferentialChangeType::TYPE_ADD) {
        $uri = new PhutilURI($this->leftRawFileURI);
        $uri = $this->appendDefaultQueryParams($uri, $qparams);
        $meta['leftURI'] = (string)$uri;
      }
    }

    if ($this->rightRawFileURI) {
      if ($change != DifferentialChangeType::TYPE_DELETE &&
          $change != DifferentialChangeType::TYPE_MULTICOPY) {
        $uri = new PhutilURI($this->rightRawFileURI);
        $uri = $this->appendDefaultQueryParams($uri, $qparams);
        $meta['rightURI'] = (string)$uri;
      }
    }

    $meta['containerID'] = $detail->getID();

    return id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('View Options'))
      ->setIcon('fa-bars')
      ->setColor(PHUIButtonView::GREY)
      ->setHref(idx($meta, 'detailURI', '#'))
      ->setMetadata($meta)
      ->addSigil('differential-view-options');
  }

  private function appendDefaultQueryParams(PhutilURI $uri, array $params) {
    // Add these default query parameters to the query string if they do not
    // already exist.

    $have = array();
    foreach ($uri->getQueryParamsAsPairList() as $pair) {
      list($key, $value) = $pair;
      $have[$key] = true;
    }

    foreach ($params as $key => $value) {
      if (!isset($have[$key])) {
        $uri->appendQueryParam($key, $value);
      }
    }

    return $uri;
  }

}
