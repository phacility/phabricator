<?php

final class DifferentialChangesetDetailView extends AphrontView {

  private $changeset;
  private $buttons = array();
  private $editable;
  private $symbolIndex;
  private $id;
  private $vsChangesetID;
  private $renderURI;
  private $renderingRef;
  private $autoload;
  private $repository;
  private $diff;
  private $changesetResponse;
  private $branch;

  public function setAutoload($autoload) {
    $this->autoload = $autoload;
    return $this;
  }

  public function getAutoload() {
    return $this->autoload;
  }

  public function setRenderingRef($rendering_ref) {
    $this->renderingRef = $rendering_ref;
    return $this;
  }

  public function getRenderingRef() {
    return $this->renderingRef;
  }

  public function setChangesetResponse(PhabricatorChangesetResponse $response) {
    $this->changesetResponse = $response;
    return $this;
  }

  public function getChangesetResponse() {
    return $this->changesetResponse;
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

  public function setBranch($branch) {
    $this->branch = $branch;
    return $this;
  }

  public function getBranch() {
    return $this->branch;
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
    $viewer = $this->getViewer();

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

    $changeset_id = $this->changeset->getID();

    $vs_id = $this->getVsChangesetID();
    if (!$vs_id) {
      // Showing a changeset normally.
      $left_id = $changeset_id;
      $right_id = $changeset_id;
    } else if ($vs_id == -1) {
      // Showing a synthetic "deleted" changeset for a file which was
      // removed between changes.
      $left_id = $changeset_id;
      $right_id = null;
    } else {
      // Showing a diff-of-diffs.
      $left_id = $vs_id;
      $right_id = $changeset_id;
    }

    // In the persistent banner, emphasize the current filename.
    $path_part = dirname($display_filename);
    $file_part = basename($display_filename);
    $display_parts = array();
    if (strlen($path_part)) {
      $path_part = $path_part.'/';
      $display_parts[] = phutil_tag(
        'span',
        array(
          'class' => 'diff-banner-path',
        ),
        $path_part);
    }
    $display_parts[] = phutil_tag(
      'span',
      array(
        'class' => 'diff-banner-file',
      ),
      $file_part);

    $response = $this->getChangesetResponse();
    if ($response) {
      $is_loaded = true;
      $changeset_markup = $response->getRenderedChangeset();
      $changeset_state = $response->getChangesetState();
    } else {
      $is_loaded = false;
      $changeset_markup = null;
      $changeset_state = null;
    }

    $path_parts = trim($display_filename, '/');
    $path_parts = explode('/', $path_parts);

    $show_path_uri = null;
    $show_directory_uri = null;

    $repository = $this->getRepository();
    if ($repository) {
      $diff = $this->getDiff();
      if ($diff) {
        $repo_path = $changeset->getAbsoluteRepositoryPath($repository, $diff);

        $repo_dir = dirname($repo_path);
        if ($repo_dir === $repo_path) {
          $repo_dir = null;
        }

        $show_path_uri = $repository->getDiffusionBrowseURIForPath(
          $viewer,
          $repo_path,
          idx($changeset->getMetadata(), 'line:first'),
          $this->getBranch());

        if ($repo_dir !== null) {
          $repo_dir = rtrim($repo_dir, '/').'/';

          $show_directory_uri = $repository->getDiffusionBrowseURIForPath(
            $viewer,
            $repo_dir,
            null,
            $this->getBranch());
        }
      }
    }

    if ($show_path_uri) {
      $show_path_uri = phutil_string_cast($show_path_uri);
    }

    if ($show_directory_uri) {
      $show_directory_uri = phutil_string_cast($show_directory_uri);
    }

    return javelin_tag(
      'div',
      array(
        'sigil' => 'differential-changeset',
        'meta'  => array(
          'left'  => $left_id,
          'right' => $right_id,
          'renderURI' => $this->getRenderURI(),
          'ref' => $this->getRenderingRef(),
          'autoload' => $this->getAutoload(),
          'displayPath' => hsprintf('%s', $display_parts),
          'icon' => $display_icon,
          'pathParts' => $path_parts,
          'symbolPath' => $display_filename,

          'pathIconIcon' => $changeset->getPathIconIcon(),
          'pathIconColor' => $changeset->getPathIconColor(),
          'isLowImportance' => $changeset->getIsLowImportanceChangeset(),
          'isOwned' => $changeset->getIsOwnedChangeset(),

          'editorURITemplate' => $this->getEditorURITemplate(),
          'editorConfigureURI' => $this->getEditorConfigureURI(),

          'loaded' => $is_loaded,
          'changesetState' => $changeset_state,

          'showPathURI' => $show_path_uri,
          'showDirectoryURI' => $show_directory_uri,
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
        javelin_tag(
          'h1',
          array(
            'class' => 'differential-file-icon-header',
            'sigil' => 'changeset-header',
          ),
          array(
            $icon,
            javelin_tag(
              'span',
              array(
                'class' => 'differential-changeset-path-name',
                'sigil' => 'changeset-header-path-name',
              ),
              $display_filename),
          )),
        javelin_tag(
          'div',
          array(
            'class' => 'changeset-view-content',
            'sigil' => 'changeset-view-content',
          ),
          array(
            $changeset_markup,
            $this->renderChildren(),
          )),
      ));
  }

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->repository;
  }

  public function getChangeset() {
    return $this->changeset;
  }

  public function setDiff(DifferentialDiff $diff) {
    $this->diff = $diff;
    return $this;
  }

  public function getDiff() {
    return $this->diff;
  }

  private function getEditorURITemplate() {
    $repository = $this->getRepository();
    if (!$repository) {
      return null;
    }

    $viewer = $this->getViewer();

    $link_engine = PhabricatorEditorURIEngine::newForViewer($viewer);
    if (!$link_engine) {
      return null;
    }

    $link_engine->setRepository($repository);

    $changeset = $this->getChangeset();
    $diff = $this->getDiff();

    $path = $changeset->getAbsoluteRepositoryPath($repository, $diff);
    $path = ltrim($path, '/');

    return $link_engine->getURITokensForPath($path);
  }

  private function getEditorConfigureURI() {
    $viewer = $this->getViewer();

    if (!$viewer->isLoggedIn()) {
      return null;
    }

    return '/settings/panel/editor/';
  }

}
