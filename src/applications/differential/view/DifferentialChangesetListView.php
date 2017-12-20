<?php

final class DifferentialChangesetListView extends AphrontView {

  private $changesets = array();
  private $visibleChangesets = array();
  private $references = array();
  private $inlineURI;
  private $renderURI = '/differential/changeset/';
  private $whitespace;
  private $background;
  private $header;

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

  public function setBackground($background) {
    $this->background = $background;
    return $this;
  }

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function render() {
    $viewer = $this->getViewer();

    $this->requireResource('differential-changeset-view-css');

    $changesets = $this->changesets;

    $renderer = DifferentialChangesetParser::getDefaultRendererForViewer(
      $viewer);

    $output = array();
    $ids = array();
    foreach ($changesets as $key => $changeset) {

      $file = $changeset->getFilename();
      $ref = $this->references[$key];

      $detail = id(new DifferentialChangesetDetailView())
        ->setUser($viewer);

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

      $detail->setRenderURI($this->renderURI);
      $detail->setWhitespace($this->whitespace);
      $detail->setRenderer($renderer);

      if ($this->getParser()) {
        $detail->appendChild($this->getParser()->renderChangeset());
        $detail->setLoaded(true);
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

    $this->initBehavior(
      'differential-populate',
      array(
      'changesetViewIDs' => $ids,
      'inlineURI' => $this->inlineURI,
      'inlineListURI' => $this->inlineListURI,
      'pht' => array(
        'Open in Editor' => pht('Open in Editor'),
        'Show All Context' => pht('Show All Context'),
        'All Context Shown' => pht('All Context Shown'),
        "Can't Toggle Unloaded File" => pht("Can't Toggle Unloaded File"),
        'Expand File' => pht('Expand File'),
        'Collapse File' => pht('Collapse File'),
        'Browse in Diffusion' => pht('Browse in Diffusion'),
        'View Standalone' => pht('View Standalone'),
        'Show Raw File (Left)' => pht('Show Raw File (Left)'),
        'Show Raw File (Right)' => pht('Show Raw File (Right)'),
        'Configure Editor' => pht('Configure Editor'),
        'Load Changes' => pht('Load Changes'),
        'View Side-by-Side' => pht('View Side-by-Side'),
        'View Unified' => pht('View Unified'),
        'Change Text Encoding...' => pht('Change Text Encoding...'),
        'Highlight As...' => pht('Highlight As...'),

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

        'This file content has been collapsed.' =>
          pht('This file content has been collapsed.'),
        'Show Content' => pht('Show Content'),

        'Hide or show the current file.' =>
          pht('Hide or show the current file.'),
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

        'Hide or show all inline comments.' =>
          pht('Hide or show all inline comments.'),

        'Finish editing inline comments before changing display modes.' =>
          pht('Finish editing inline comments before changing display modes.'),
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
      try {
        $meta['diffusionURI'] =
          (string)$repository->getDiffusionBrowseURIForPath(
            $viewer,
            $changeset->getAbsoluteRepositoryPath($repository, $this->diff),
            idx($changeset->getMetadata(), 'line:first'),
            $this->getBranch());
      } catch (DiffusionSetupException $e) {
        // Ignore
      }
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

    if ($viewer && $repository) {
      $path = ltrim(
        $changeset->getAbsoluteRepositoryPath($repository, $this->diff),
        '/');
      $line = idx($changeset->getMetadata(), 'line:first', 1);
      $editor_link = $viewer->loadEditorLink($path, $line, $repository);
      if ($editor_link) {
        $meta['editor'] = $editor_link;
      } else {
        $meta['editorConfigure'] = '/settings/panel/display/';
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

}
