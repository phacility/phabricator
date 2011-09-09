<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class DiffusionBrowseController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->diffusionRequest;

    $browse_query = DiffusionBrowseQuery::newFromDiffusionRequest($drequest);
    $results = $browse_query->loadPaths();

    $content = array();

    $content[] = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'browse',
      ));

    if (!$results) {

      // TODO: Format all these commits into nice VCS-agnostic links, and
      // below.
      $commit = $drequest->getCommit();
      $callsign = $drequest->getRepository()->getCallsign();
      if ($commit) {
        $commit = "r{$callsign}{$commit}";
      } else {
        $commit = 'HEAD';
      }

      switch ($browse_query->getReasonForEmptyResultSet()) {
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
          $deleted = $browse_query->getDeletedAtCommit();
          $existed = $browse_query->getExistedAtCommit();

          $deleted = "r{$callsign}{$deleted}";
          $existed = "r{$callsign}{$existed}";

          $title = 'Path Was Deleted';
          $body = "This path does not exist at {$commit}. It was deleted in ".
                  "{$deleted} and last existed at {$existed}.";
          $severity = AphrontErrorView::SEVERITY_WARNING;
          break;
        case DiffusionBrowseQuery::REASON_IS_FILE:
          $controller = new DiffusionBrowseFileController($this->getRequest());
          $controller->setDiffusionRequest($drequest);
          return $this->delegateToController($controller);
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

      $content[] = $error_view;

    } else {

      $phids = array();
      foreach ($results as $result) {
        $data = $result->getLastCommitData();
        if ($data) {
          if ($data->getCommitDetail('authorPHID')) {
            $phids[$data->getCommitDetail('authorPHID')] = true;
          }
        }
      }
      $phids = array_keys($phids);

      $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

      $browse_table = new DiffusionBrowseTableView();
      $browse_table->setDiffusionRequest($drequest);
      $browse_table->setHandles($handles);
      $browse_table->setPaths($results);

      $browse_panel = new AphrontPanelView();
      $browse_panel->appendChild($browse_table);

      $content[] = $browse_panel;
    }

    $nav = $this->buildSideNav('browse', false);
    $nav->appendChild($content);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => basename($drequest->getPath()),
      ));
  }

}
