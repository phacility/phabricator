<?php

final class DiffusionEmptyResultView extends DiffusionView {

  private $browseResultSet;
  private $view;

  public function setDiffusionBrowseResultSet(DiffusionBrowseResultSet $set) {
    $this->browseResultSet = $set;
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

    $reason = $this->browseResultSet->getReasonForEmptyResultSet();
    switch ($reason) {
      case DiffusionBrowseResultSet::REASON_IS_NONEXISTENT:
        $title = pht('Path Does Not Exist');
        // TODO: Under git, this error message should be more specific. It
        // may exist on some other branch.
        $body  = pht('This path does not exist anywhere.');
        $severity = PHUIInfoView::SEVERITY_ERROR;
        break;
      case DiffusionBrowseResultSet::REASON_IS_EMPTY:
        $title = pht('Empty Directory');
        $body = pht("This path was an empty directory at %s.\n", $commit);
        $severity = PHUIInfoView::SEVERITY_NOTICE;
        break;
      case DiffusionBrowseResultSet::REASON_IS_DELETED:
        $deleted = $this->browseResultSet->getDeletedAtCommit();
        $existed = $this->browseResultSet->getExistedAtCommit();

        $browse = $this->linkBrowse(
          $drequest->getPath(),
          array(
            'text' => 'existed',
            'commit' => $existed,
            'params' => array('view' => $this->view),
          ));

        $title = pht('Path Was Deleted');
        $body = pht(
          'This path does not exist at %s. It was deleted in %s and last %s '.
            'at %s.',
          $commit,
          self::linkCommit($drequest->getRepository(), $deleted),
          $browse,
          "r{$callsign}{$existed}");
        $severity = PHUIInfoView::SEVERITY_WARNING;
        break;
      case DiffusionBrowseResultSet::REASON_IS_UNTRACKED_PARENT:
        $subdir = $drequest->getRepository()->getDetail('svn-subpath');
        $title = pht('Directory Not Tracked');
        $body =
          pht("This repository is configured to track only one subdirectory ".
          "of the entire repository ('%s'), ".
          "but you aren't looking at something in that subdirectory, so no ".
          "information is available.", $subdir);
        $severity = PHUIInfoView::SEVERITY_WARNING;
        break;
      default:
        throw new Exception("Unknown failure reason: $reason");
    }

    $error_view = new PHUIInfoView();
    $error_view->setSeverity($severity);
    $error_view->setTitle($title);
    $error_view->appendChild(phutil_tag('p', array(), $body));

    return $error_view->render();
  }

}
