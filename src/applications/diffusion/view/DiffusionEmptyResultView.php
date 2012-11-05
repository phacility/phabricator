<?php

final class DiffusionEmptyResultView extends DiffusionView {

  private $browseQuery;
  private $view;

  public function setBrowseQuery($browse_query) {
    $this->browseQuery = $browse_query;
    return $this;
  }

  public function setView($view) {
    $this->view = $view;
    return $this;
  }

  public function render() {
    $drequest = $this->getDiffusionRequest();

    $commit = $drequest->getCommit();
    $callsign = $drequest->getRepository()->getCallsign();
    if ($commit) {
      $commit = "r{$callsign}{$commit}";
    } else {
      $commit = 'HEAD';
    }

    switch ($this->browseQuery->getReasonForEmptyResultSet()) {
      case DiffusionBrowseQuery::REASON_IS_NONEXISTENT:
        $title = 'Path Does Not Exist';
        // TODO: Under git, this error message should be more specific. It
        // may exist on some other branch.
        $body  = "This path does not exist anywhere.";
        $severity = AphrontErrorView::SEVERITY_ERROR;
        break;
      case DiffusionBrowseQuery::REASON_IS_EMPTY:
        $title = 'Empty Directory';
        $body = "This path was an empty directory at {$commit}.\n";
        $severity = AphrontErrorView::SEVERITY_NOTICE;
        break;
      case DiffusionBrowseQuery::REASON_IS_DELETED:
        $deleted = $this->browseQuery->getDeletedAtCommit();
        $existed = $this->browseQuery->getExistedAtCommit();

        $deleted = self::linkCommit($drequest->getRepository(), $deleted);

        $browse = $this->linkBrowse(
          $drequest->getPath(),
          array(
            'text' => 'existed',
            'commit' => $existed,
            'params' => array('view' => $this->view),
          )
        );

        $existed = "r{$callsign}{$existed}";

        $title = 'Path Was Deleted';
        $body = "This path does not exist at {$commit}. It was deleted in ".
                "{$deleted} and last {$browse} at {$existed}.";
        $severity = AphrontErrorView::SEVERITY_WARNING;
        break;
      case DiffusionBrowseQuery::REASON_IS_UNTRACKED_PARENT:
        $subdir = $drequest->getRepository()->getDetail('svn-subpath');
        $title = 'Directory Not Tracked';
        $body =
          "This repository is configured to track only one subdirectory ".
          "of the entire repository ('".phutil_escape_html($subdir)."'), ".
          "but you aren't looking at something in that subdirectory, so no ".
          "information is available.";
        $severity = AphrontErrorView::SEVERITY_WARNING;
        break;
      default:
        throw new Exception("Unknown failure reason!");
    }

    $error_view = new AphrontErrorView();
    $error_view->setSeverity($severity);
    $error_view->setTitle($title);
    $error_view->appendChild('<p>'.$body.'</p>');

    return $error_view->render();
  }

}
