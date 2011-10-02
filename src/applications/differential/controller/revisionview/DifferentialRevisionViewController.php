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

class DifferentialRevisionViewController extends DifferentialController {

  private $revisionID;

  public function willProcessRequest(array $data) {
    $this->revisionID = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $revision = id(new DifferentialRevision())->load($this->revisionID);
    if (!$revision) {
      return new Aphront404Response();
    }

    $revision->loadRelationships();

    $diffs = $revision->loadDiffs();

    if (!$diffs) {
      throw new Exception(
        "This revision has no diffs. Something has gone quite wrong.");
    }

    $diff_vs = $request->getInt('vs');

    $target = end($diffs);
    $target_id = $request->getInt('id');
    if ($target_id) {
      if (isset($diffs[$target_id])) {
        $target = $diffs[$target_id];
      }
    }

    $diffs = mpull($diffs, null, 'getID');
    if (empty($diffs[$diff_vs])) {
      $diff_vs = null;
    }

    list($aux_fields, $props) = $this->loadAuxiliaryFieldsAndProperties(
      $revision,
      $target,
      array(
        'local:commits',
      ));

    list($changesets, $vs_map, $rendering_references) =
      $this->loadChangesetsAndVsMap($diffs, $diff_vs, $target);

    $comments = $revision->loadComments();
    $comments = array_merge(
      $this->getImplicitComments($revision),
      $comments);

    $all_changesets = $changesets;
    $inlines = $this->loadInlineComments($comments, $all_changesets);

    $object_phids = array_merge(
      $revision->getReviewers(),
      $revision->getCCPHIDs(),
      $revision->loadCommitPHIDs(),
      array(
        $revision->getAuthorPHID(),
        $user->getPHID(),
      ),
      mpull($comments, 'getAuthorPHID'));

    foreach ($comments as $comment) {
      $metadata = $comment->getMetadata();
      $added_reviewers = idx(
        $metadata,
        DifferentialComment::METADATA_ADDED_REVIEWERS);
      if ($added_reviewers) {
        foreach ($added_reviewers as $phid) {
          $object_phids[] = $phid;
        }
      }
      $added_ccs = idx(
        $metadata,
        DifferentialComment::METADATA_ADDED_CCS);
      if ($added_ccs) {
        foreach ($added_ccs as $phid) {
          $object_phids[] = $phid;
        }
      }
    }

    foreach ($revision->getAttached() as $type => $phids) {
      foreach ($phids as $phid => $info) {
        $object_phids[] = $phid;
      }
    }

    $aux_phids = array();
    foreach ($aux_fields as $key => $aux_field) {
      $aux_phids[$key] = $aux_field->getRequiredHandlePHIDsForRevisionView();
    }
    $object_phids = array_merge($object_phids, array_mergev($aux_phids));
    $object_phids = array_unique($object_phids);

    $handles = id(new PhabricatorObjectHandleData($object_phids))
      ->loadHandles();

    foreach ($aux_fields as $key => $aux_field) {
      // Make sure each field only has access to handles it specifically
      // requested, not all handles. Otherwise you can get a field which works
      // only in the presence of other fields.
      $aux_field->setHandles(array_select_keys($handles, $aux_phids[$key]));
    }

    $request_uri = $request->getRequestURI();

    $limit = 100;
    $large = $request->getStr('large');
    if (count($changesets) > $limit && !$large) {
      $count = number_format(count($changesets));
      $warning = new AphrontErrorView();
      $warning->setTitle('Very Large Diff');
      $warning->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $warning->setWidth(AphrontErrorView::WIDTH_WIDE);
      $warning->appendChild(
        "<p>This diff is very large and affects {$count} files. Use ".
        "Table of Contents to open files in a standalone view. ".
        "<strong>".
          phutil_render_tag(
            'a',
            array(
              'href' => $request_uri->alter('large', 'true'),
            ),
            'Show All Files Inline').
        "</strong>");
      $warning = $warning->render();

      $visible_changesets = array();
    } else {
      $warning = null;
      $visible_changesets = $changesets;
    }

    $revision_detail = new DifferentialRevisionDetailView();
    $revision_detail->setRevision($revision);
    $revision_detail->setAuxiliaryFields($aux_fields);

    $actions = $this->getRevisionActions($revision);

    $custom_renderer_class = PhabricatorEnv::getEnvConfig(
      'differential.revision-custom-detail-renderer');
    if ($custom_renderer_class) {

      // TODO: build a better version of the action links and deprecate the
      // whole DifferentialRevisionDetailRenderer class.
      PhutilSymbolLoader::loadClass($custom_renderer_class);
      $custom_renderer =
        newv($custom_renderer_class, array());
      $actions = array_merge(
        $actions,
        $custom_renderer->generateActionLinks($revision, $target));
    }

    $whitespace = $request->getStr(
      'whitespace',
      DifferentialChangesetParser::WHITESPACE_IGNORE_ALL);

    $symbol_indexes = $this->buildSymbolIndexes($target, $visible_changesets);

    $revision_detail->setActions($actions);
    $revision_detail->setUser($user);

    $comment_view = new DifferentialRevisionCommentListView();
    $comment_view->setComments($comments);
    $comment_view->setHandles($handles);
    $comment_view->setInlineComments($inlines);
    $comment_view->setChangesets($all_changesets);
    $comment_view->setUser($user);
    $comment_view->setTargetDiff($target);

    $changeset_view = new DifferentialChangesetListView();
    $changeset_view->setChangesets($visible_changesets);
    $changeset_view->setEditable(true);
    $changeset_view->setStandaloneViews(true);
    $changeset_view->setRevision($revision);
    $changeset_view->setRenderingReferences($rendering_references);
    $changeset_view->setWhitespace($whitespace);
    $changeset_view->setSymbolIndexes($symbol_indexes);

    $diff_history = new DifferentialRevisionUpdateHistoryView();
    $diff_history->setDiffs($diffs);
    $diff_history->setSelectedVersusDiffID($diff_vs);
    $diff_history->setSelectedDiffID($target->getID());
    $diff_history->setSelectedWhitespace($whitespace);

    $local_view = new DifferentialLocalCommitsView();
    $local_view->setUser($user);
    $local_view->setLocalCommits(idx($props, 'local:commits'));

    $toc_view = new DifferentialDiffTableOfContentsView();
    $toc_view->setChangesets($changesets);
    $toc_view->setStandaloneViewLink(empty($visible_changesets));
    $toc_view->setVsMap($vs_map);
    $toc_view->setRevisionID($revision->getID());
    $toc_view->setWhitespace($whitespace);

    $draft = id(new PhabricatorDraft())->loadOneWhere(
      'authorPHID = %s AND draftKey = %s',
      $user->getPHID(),
      'differential-comment-'.$revision->getID());
    if ($draft) {
      $draft = $draft->getDraft();
    } else {
      $draft = null;
    }

    $comment_form = new DifferentialAddCommentView();
    $comment_form->setRevision($revision);
    $comment_form->setActions($this->getRevisionCommentActions($revision));
    $comment_form->setActionURI('/differential/comment/save/');
    $comment_form->setUser($user);
    $comment_form->setDraft($draft);

    $this->updateViewTime($user->getPHID(), $revision->getPHID());

    $pane_id = celerity_generate_unique_node_id();
    Javelin::initBehavior(
      'differential-keyboard-navigation',
      array(
        'haunt' => $pane_id,
      ));

    return $this->buildStandardPageResponse(
      id(new DifferentialPrimaryPaneView())
        ->setLineWidthFromChangesets($changesets)
        ->setID($pane_id)
        ->appendChild(
          $revision_detail->render().
          $comment_view->render().
          $diff_history->render().
          $warning.
          $local_view->render().
          $toc_view->render().
          $changeset_view->render().
          $comment_form->render()),
      array(
        'title' => 'D'.$revision->getID().' '.$revision->getTitle(),
      ));
  }

  private function getImplicitComments(DifferentialRevision $revision) {

    $template = new DifferentialComment();
    $template->setAuthorPHID($revision->getAuthorPHID());
    $template->setRevisionID($revision->getID());
    $template->setDateCreated($revision->getDateCreated());

    $comments = array();

    if (strlen($revision->getSummary())) {
      $summary_comment = clone $template;
      $summary_comment->setContent($revision->getSummary());
      $summary_comment->setAction(DifferentialAction::ACTION_SUMMARIZE);
      $comments[] = $summary_comment;
    }

    if (strlen($revision->getTestPlan())) {
      $testplan_comment = clone $template;
      $testplan_comment->setContent($revision->getTestPlan());
      $testplan_comment->setAction(DifferentialAction::ACTION_TESTPLAN);
      $comments[] = $testplan_comment;
    }

    return $comments;
  }

  private function getRevisionActions(DifferentialRevision $revision) {
    $viewer_phid = $this->getRequest()->getUser()->getPHID();
    $viewer_is_owner = ($revision->getAuthorPHID() == $viewer_phid);
    $viewer_is_reviewer = in_array($viewer_phid, $revision->getReviewers());
    $viewer_is_cc = in_array($viewer_phid, $revision->getCCPHIDs());
    $status = $revision->getStatus();
    $revision_id = $revision->getID();
    $revision_phid = $revision->getPHID();

    $links = array();

    if ($viewer_is_owner) {
      $links[] = array(
        'class' => 'revision-edit',
        'href'  => "/differential/revision/edit/{$revision_id}/",
        'name'  => 'Edit Revision',
      );
    }

    if (!$viewer_is_owner && !$viewer_is_reviewer) {
      $action = $viewer_is_cc ? 'rem' : 'add';
      $links[] = array(
        'class'   => $viewer_is_cc ? 'subscribe-rem' : 'subscribe-add',
        'href'    => "/differential/subscribe/{$action}/{$revision_id}/",
        'name'    => $viewer_is_cc ? 'Unsubscribe' : 'Subscribe',
        'instant' => true,
      );
    } else {
      $links[] = array(
        'class' => 'subscribe-rem unavailable',
        'name'  => 'Automatically Subscribed',
      );
    }

    require_celerity_resource('phabricator-object-selector-css');
    require_celerity_resource('javelin-behavior-phabricator-object-selector');

    $links[] = array(
      'class' => 'action-dependencies',
      'name'  => 'Edit Dependencies',
      'href'  => "/search/attach/{$revision_phid}/DREV/dependencies/",
      'sigil' => 'workflow',
    );

    if (PhabricatorEnv::getEnvConfig('maniphest.enabled')) {
      $links[] = array(
        'class' => 'attach-maniphest',
        'name'  => 'Edit Maniphest Tasks',
        'href'  => "/search/attach/{$revision_phid}/TASK/",
        'sigil' => 'workflow',
      );
    }

    $links[] = array(
      'class' => 'transcripts-metamta',
      'name'  => 'MetaMTA Transcripts',
      'href'  => "/mail/?phid={$revision_phid}",
    );

    $links[] = array(
      'class' => 'transcripts-herald',
      'name'  => 'Herald Transcripts',
      'href'  => "/herald/transcript/?phid={$revision_phid}",
    );

    return $links;
  }

  private function getRevisionCommentActions(DifferentialRevision $revision) {

    $actions = array(
      DifferentialAction::ACTION_COMMENT => true,
    );

    $viewer_phid = $this->getRequest()->getUser()->getPHID();
    $viewer_is_owner = ($viewer_phid == $revision->getAuthorPHID());
    $viewer_is_reviewer = in_array($viewer_phid, $revision->getReviewers());

    if ($viewer_is_owner) {
      switch ($revision->getStatus()) {
        case DifferentialRevisionStatus::NEEDS_REVIEW:
          $actions[DifferentialAction::ACTION_ABANDON] = true;
          $actions[DifferentialAction::ACTION_RETHINK] = true;
          break;
        case DifferentialRevisionStatus::NEEDS_REVISION:
          $actions[DifferentialAction::ACTION_ABANDON] = true;
          $actions[DifferentialAction::ACTION_REQUEST] = true;
          break;
        case DifferentialRevisionStatus::ACCEPTED:
          $actions[DifferentialAction::ACTION_ABANDON] = true;
          $actions[DifferentialAction::ACTION_REQUEST] = true;
          $actions[DifferentialAction::ACTION_RETHINK] = true;
          break;
        case DifferentialRevisionStatus::COMMITTED:
          break;
        case DifferentialRevisionStatus::ABANDONED:
          $actions[DifferentialAction::ACTION_RECLAIM] = true;
          break;
      }
    } else {
      switch ($revision->getStatus()) {
        case DifferentialRevisionStatus::NEEDS_REVIEW:
          $actions[DifferentialAction::ACTION_ACCEPT] = true;
          $actions[DifferentialAction::ACTION_REJECT] = true;
          $actions[DifferentialAction::ACTION_RESIGN] = $viewer_is_reviewer;
          break;
        case DifferentialRevisionStatus::NEEDS_REVISION:
          $actions[DifferentialAction::ACTION_ACCEPT] = true;
          $actions[DifferentialAction::ACTION_RESIGN] = $viewer_is_reviewer;
          break;
        case DifferentialRevisionStatus::ACCEPTED:
          $actions[DifferentialAction::ACTION_REJECT] = true;
          break;
        case DifferentialRevisionStatus::COMMITTED:
        case DifferentialRevisionStatus::ABANDONED:
          break;
      }
    }

    $actions[DifferentialAction::ACTION_ADDREVIEWERS] = true;
    $actions[DifferentialAction::ACTION_ADDCCS] = true;

    return array_keys(array_filter($actions));
  }

  private function loadInlineComments(array $comments, array &$changesets) {

    $inline_comments = array();

    $comment_ids = array_filter(mpull($comments, 'getID'));
    if (!$comment_ids) {
      return $inline_comments;
    }

    $inline_comments = id(new DifferentialInlineComment())
      ->loadAllWhere(
        'commentID in (%Ld)',
        $comment_ids);

    $load_changesets = array();
    foreach ($inline_comments as $inline) {
      $changeset_id = $inline->getChangesetID();
      if (isset($changesets[$changeset_id])) {
        continue;
      }
      $load_changesets[$changeset_id] = true;
    }

    $more_changesets = array();
    if ($load_changesets) {
      $changeset_ids = array_keys($load_changesets);
      $more_changesets += id(new DifferentialChangeset())
        ->loadAllWhere(
          'id IN (%Ld)',
          $changeset_ids);
    }

    if ($more_changesets) {
      $changesets += $more_changesets;
      $changesets = msort($changesets, 'getSortKey');
    }

    return $inline_comments;
  }

  private function loadChangesetsAndVsMap(array $diffs, $diff_vs, $target) {
    $load_ids = array();
    if ($diff_vs) {
      $load_ids[] = $diff_vs;
    }
    $load_ids[] = $target->getID();

    $raw_changesets = id(new DifferentialChangeset())
      ->loadAllWhere(
        'diffID IN (%Ld)',
        $load_ids);
    $changeset_groups = mgroup($raw_changesets, 'getDiffID');

    $changesets = idx($changeset_groups, $target->getID(), array());
    $changesets = mpull($changesets, null, 'getID');

    $refs = array();
    foreach ($changesets as $changeset) {
      $refs[$changeset->getID()] = $changeset->getID();
    }

    $vs_map = array();
    if ($diff_vs) {
      $vs_changesets = idx($changeset_groups, $diff_vs, array());
      $vs_changesets = mpull($vs_changesets, null, 'getFilename');
      foreach ($changesets as $key => $changeset) {
        $file = $changeset->getFilename();
        if (isset($vs_changesets[$file])) {
          $vs_map[$changeset->getID()] = $vs_changesets[$file]->getID();
          $refs[$changeset->getID()] =
            $changeset->getID().'/'.$vs_changesets[$file]->getID();
          unset($vs_changesets[$file]);
        } else {
          $refs[$changeset->getID()] = $changeset->getID();
        }
      }
      foreach ($vs_changesets as $changeset) {
        $changesets[$changeset->getID()] = $changeset;
        $vs_map[$changeset->getID()] = -1;
        $refs[$changeset->getID()] = $changeset->getID().'/-1';
      }
    }

    $changesets = msort($changesets, 'getSortKey');

    return array($changesets, $vs_map, $refs);
  }

  private function updateViewTime($user_phid, $revision_phid) {
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    $view_time =
      id(new DifferentialViewTime())
        ->setViewerPHID($user_phid)
        ->setObjectPHID($revision_phid)
        ->setViewTime(time())
        ->replace();
  }

  private function loadAuxiliaryFieldsAndProperties(
    DifferentialRevision $revision,
    DifferentialDiff $diff,
    array $special_properties) {

    $aux_fields = DifferentialFieldSelector::newSelector()
      ->getFieldSpecifications();
    foreach ($aux_fields as $key => $aux_field) {
      if (!$aux_field->shouldAppearOnRevisionView()) {
        unset($aux_fields[$key]);
      }
    }

    $aux_fields = DifferentialAuxiliaryField::loadFromStorage(
      $revision,
      $aux_fields);

    $aux_props = array();
    foreach ($aux_fields as $key => $aux_field) {
      $aux_field->setDiff($diff);
      $aux_props[$key] = $aux_field->getRequiredDiffProperties();
    }

    $required_properties = array_mergev($aux_props);
    $required_properties = array_merge(
      $required_properties,
      $special_properties);

    $property_map = array();
    if ($required_properties) {
      $properties = id(new DifferentialDiffProperty())->loadAllWhere(
        'diffID = %d AND name IN (%Ls)',
        $diff->getID(),
        $required_properties);
      $property_map = mpull($properties, 'getData', 'getName');
    }

    foreach ($aux_fields as $key => $aux_field) {
      // Give each field only the properties it specifically required, and
      // set 'null' for each requested key which we didn't actually load a
      // value for (otherwise, getDiffProperty() will throw).
      if ($aux_props[$key]) {
        $props = array_select_keys($property_map, $aux_props[$key]) +
                 array_fill_keys($aux_props[$key], null);
      } else {
        $props = array();
      }

      $aux_field->setDiffProperties($props);
    }

    return array(
      $aux_fields,
      array_select_keys(
        $property_map,
        $special_properties));
  }

  private function buildSymbolIndexes(
    DifferentialDiff $target,
    array $visible_changesets) {

    $engine = PhabricatorSyntaxHighlighter::newEngine();

    $symbol_indexes = array();
    $arc_project = $target->loadArcanistProject();
    if (!$arc_project) {
      return array();
    }

    $langs = $arc_project->getSymbolIndexLanguages();
    if (!$langs) {
      return array();
    }

    $project_phids = array_merge(
      array($arc_project->getPHID()),
      nonempty($arc_project->getSymbolIndexProjects(), array()));

    $indexed_langs = array_fill_keys($langs, true);
    foreach ($visible_changesets as $key => $changeset) {
      $lang = $engine->getLanguageFromFilename($changeset->getFileName());
      if (isset($indexed_langs[$lang])) {
        $symbol_indexes[$key] = array(
          'lang'      => $lang,
          'projects'  => $project_phids,
        );
      }
    }

    return $symbol_indexes;
  }


}
