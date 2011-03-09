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

    if (!$results) {

      switch ($browse_query->getReasonForEmptyResultSet()) {
        case DiffusionBrowseQuery::REASON_IS_NONEXISTENT:
          $title = 'Path Does Not Exist';
          // TODO: Under git, this error message should be more specific. It
          // may exist on some other branch.
          $body  = "This path does not exist anywhere.";
          $severity = AphrontErrorView::SEVERITY_ERROR;
          break;
        case DiffusionBrowseQuery::REASON_IS_DELETED:
          // TODO: Format all these commits into nice VCS-agnostic links.
          $commit = $drequest->getCommit();
          $deleted = $browse_query->getDeletedAtCommit();
          $existed = $browse_query->getExistedAtCommit();

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
        default:
          throw new Exception("Unknown failure reason!");
      }

      $error_view = new AphrontErrorView();
      $error_view->setSeverity($severity);
      $error_view->setTitle($title);
      $error_view->appendChild('<p>'.$body.'</p>');

      $content[] = $error_view;

    } else {
      $browse_table = new DiffusionBrowseTableView();
      $browse_table->setDiffusionRequest($drequest);
      $browse_table->setPaths($results);

      $browse_panel = new AphrontPanelView();
      $browse_panel->setHeader($drequest->getPath());
      $browse_panel->appendChild($browse_table);

      $content[] = $browse_panel;

      // TODO: Branch table
    }

    // TODO: Crumbs
    // TODO: Side nav

    return $this->buildStandardPageResponse(
      $content,
      array(
        'title' => basename($drequest->getPath()),
      ));
  }

}
