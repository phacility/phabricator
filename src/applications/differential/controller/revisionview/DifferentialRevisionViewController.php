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

    $revision = id(new DifferentialRevision())->load($this->revisionID);
    if (!$revision) {
      return new Aphront404Response();
    }

    $revision->loadRelationships();

    $diffs = $revision->loadDiffs();

    $diff_vs = $request->getInt('vs');
    $target = end($diffs);

    $diffs = mpull($diffs, null, 'getID');
    if (empty($diffs[$diff_vs])) {
      $diff_vs = null;
    }

    list($changesets, $vs_map) =
      $this->loadChangesetsAndVsMap($diffs, $diff_vs, $target);

    $comments = $revision->loadComments();
    $comments = array_merge(
      $this->getImplicitComments($revision),
      $comments);

    $inlines = $this->loadInlineComments($comments, $changesets);

    $object_phids = array_merge(
      $revision->getReviewers(),
      $revision->getCCPHIDs(),
      array(
        $revision->getAuthorPHID(),
        $request->getUser()->getPHID(),
      ),
      mpull($comments, 'getAuthorPHID'));
    $object_phids = array_unique($object_phids);

    $handles = id(new PhabricatorObjectHandleData($object_phids))
      ->loadHandles();

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
        "<p>This diff is very large and affects {$count} files. Only ".
        "the first {$limit} files are shown. ".
        "<strong>".
          phutil_render_tag(
            'a',
            array(
              'href' => $request_uri->alter('large', 'true'),
            ),
            'Show All Files').
        "</strong>");
      $warning = $warning->render();

      $visible_changesets = array_slice($changesets, 0, $limit, true);
    } else {
      $warning = null;
      $visible_changesets = $changesets;
    }

    $revision_detail = new DifferentialRevisionDetailView();
    $revision_detail->setRevision($revision);

    $properties = $this->getRevisionProperties($revision, $target, $handles);
    $revision_detail->setProperties($properties);

    $actions = $this->getRevisionActions($revision);
    $revision_detail->setActions($actions);

    $comment_view = new DifferentialRevisionCommentListView();
    $comment_view->setComments($comments);
    $comment_view->setHandles($handles);
    $comment_view->setInlineComments($inlines);
    $comment_view->setChangesets($changesets);
    $comment_view->setUser($request->getUser());

    $diff_history = new DifferentialRevisionUpdateHistoryView();
    $diff_history->setDiffs($diffs);
    $diff_history->setSelectedVersusDiffID($diff_vs);
    $diff_history->setSelectedDiffID($target->getID());

    $toc_view = new DifferentialDiffTableOfContentsView();
    $toc_view->setChangesets($changesets);

    $changeset_view = new DifferentialChangesetListView();
    $changeset_view->setChangesets($visible_changesets);
    $changeset_view->setEditable(true);
    $changeset_view->setRevision($revision);
    $changeset_view->setVsMap($vs_map);

    $comment_form = new DifferentialAddCommentView();
    $comment_form->setRevision($revision);
    $comment_form->setActions($this->getRevisionCommentActions($revision));
    $comment_form->setActionURI('/differential/comment/save/');
    $comment_form->setUser($request->getUser());

    return $this->buildStandardPageResponse(
      '<div class="differential-primary-pane">'.
        $revision_detail->render().
        $comment_view->render().
        $diff_history->render().
        $toc_view->render().
        $warning.
        $changeset_view->render().
        $comment_form->render().
      '</div>',
      array(
        'title' => $revision->getTitle(),
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

  private function getRevisionProperties(
    DifferentialRevision $revision,
    DifferentialDiff $diff,
    array $handles) {

    $properties = array();

    $status = $revision->getStatus();
    $status = DifferentialRevisionStatus::getNameForRevisionStatus($status);
    $properties['Revision Status'] = '<strong>'.$status.'</strong>';

    $author = $handles[$revision->getAuthorPHID()];
    $properties['Author'] = $author->renderLink();

    $properties['Reviewers'] = $this->renderHandleLinkList(
      array_select_keys(
        $handles,
        $revision->getReviewers()));

    $properties['CCs'] = $this->renderHandleLinkList(
      array_select_keys(
        $handles,
        $revision->getCCPHIDs()));

    $host = $diff->getSourceMachine();
    if ($host) {
      $properties['Host'] = phutil_escape_html($host);
    }

    $path = $diff->getSourcePath();
    if ($path) {
      $branch = $diff->getBranch() ? ' ('.$diff->getBranch().')' : '';
      $properties['Path'] = phutil_escape_html("{$path} {$branch}");
    }

    $lstar = DifferentialRevisionUpdateHistoryView::renderDiffLintStar($diff);
    $lmsg = DifferentialRevisionUpdateHistoryView::getDiffLintMessage($diff);
    $properties['Lint'] = $lstar.' '.$lmsg;

    $ustar = DifferentialRevisionUpdateHistoryView::renderDiffUnitStar($diff);
    $umsg = DifferentialRevisionUpdateHistoryView::getDiffUnitMessage($diff);
    $properties['Unit'] = $ustar.' '.$umsg;

    return $properties;
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
        'class' => $viewer_is_cc ? 'subscribe-rem' : 'subscribe-add',
        'href'  => "/differential/subscribe/{$action}/{$revision_id}/",
        'name'  => $viewer_is_cc ? 'Unsubscribe' : 'Subscribe',
      );
    } else {
      $links[] = array(
        'class' => 'subscribe-rem unavailable',
        'name'  => 'Automatically Subscribed',
      );
    }

    $links[] = array(
      'class' => 'transcripts-metamta',
      'name'  => 'MetaMTA Transcripts',
      'href'  => "/mail/?phid={$revision_phid}",
    );

    return $links;
  }


  private function renderHandleLinkList(array $list) {
    if (empty($list)) {
      return '<em>None</em>';
    }
    return implode(', ', mpull($list, 'renderLink'));
  }

  private function getRevisionCommentActions(DifferentialRevision $revision) {

    $actions = array(
      DifferentialAction::ACTION_COMMENT => true,
    );

    $viewer_phid = $this->getRequest()->getUser()->getPHID();
    $viewer_is_owner = ($viewer_phid == $revision->getAuthorPHID());

    if ($viewer_is_owner) {
      switch ($revision->getStatus()) {
        case DifferentialRevisionStatus::NEEDS_REVIEW:
          $actions[DifferentialAction::ACTION_ABANDON] = true;
          break;
        case DifferentialRevisionStatus::NEEDS_REVISION:
        case DifferentialRevisionStatus::ACCEPTED:
          $actions[DifferentialAction::ACTION_ABANDON] = true;
          $actions[DifferentialAction::ACTION_REQUEST] = true;
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
          break;
        case DifferentialRevisionStatus::NEEDS_REVISION:
          $actions[DifferentialAction::ACTION_ACCEPT] = true;
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

    return array_keys($actions);
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

    $vs_map = array();
    if ($diff_vs) {
      $vs_changesets = idx($changeset_groups, $diff_vs, array());
      $vs_changesets = mpull($vs_changesets, null, 'getFilename');
      foreach ($changesets as $key => $changeset) {
        $file = $changeset->getFilename();
        if (isset($vs_changesets[$file])) {
          $vs_map[$changeset->getID()] = $vs_changesets[$file]->getID();
          unset($vs_changesets[$file]);
        }
      }
      foreach ($vs_changesets as $changeset) {
        $changesets[$changeset->getID()] = $changeset;
        $vs_map[$changeset->getID()] = -1;
      }
    }

    $changesets = msort($changesets, 'getSortKey');

    return array($changesets, $vs_map);
  }

}
/*


  protected function getRevisionActions(DifferentialRevision $revision) {

    $viewer_id = $this->getRequest()->getViewerContext()->getUserID();
    $viewer_is_owner = ($viewer_id == $revision->getOwnerID());
    $viewer_is_reviewer =
      ((array_search($viewer_id, $revision->getReviewers())) !== false);
    $viewer_is_cc =
      ((array_search($viewer_id, $revision->getCCFBIDs())) !== false);
    $status = $revision->getStatus();

    $links = array();

    if (!$viewer_is_owner && !$viewer_is_reviewer) {
      $action = $viewer_is_cc
        ? 'rem'
        : 'add';
      $revision_id = $revision->getID();
      $href = "/differential/subscribe/{$action}/{$revision_id}";
      $links[] = array(
        $viewer_is_cc ? 'subscribe-disabled' : 'subscribe-enabled',
        <a href={$href}>{$viewer_is_cc ? 'Unsubscribe' : 'Subscribe'}</a>,
      );
    } else {
      $links[] = array(
        'subscribe-disabled unavailable',
        <a>Automatically Subscribed</a>,
      );
    }

    $blast_uri = RedirectURI(
      '/intern/differential/?action=tasks&fbid='.$revision->getFBID())
      ->setTier('intern');
    $links[] = array(
      'tasks',
      <a href={$blast_uri}>Edit Tasks</a>,
    );

    $engineering_repository_id = RepositoryRef::getByCallsign('E')->getID();
    $svn_revision = $revision->getSVNRevision();
    if ($status == DifferentialConstants::COMMITTED &&
        $svn_revision &&
        $revision->getRepositoryID() == $engineering_repository_id) {
      $href = '/intern/push/request.php?rev='.$svn_revision;
      $href = RedirectURI($href)->setTier('intern');
      $links[] = array(
        'merge',
        <a href={$href} id="ask_for_merge_link">Ask for Merge</a>,
      );
    }

    $links[] = array(
      'herald-transcript',
      <a href={"/herald/transcript/?fbid=".$revision->getFBID()}
        >Herald Transcripts</a>,
    );
    $links[] = array(
      'metamta-transcript',
      <a href={"/mail/?view=all&fbid=".$revision->getFBID()}
        >MetaMTA Transcripts</a>,
    );


    $list = <ul class="differential-actions" />;
    foreach ($links as $link) {
      list($class, $tag) = $link;
      $list->appendChild(<li class={$class}>{$tag}</li>);
    }

    return $list;




  protected function getSandcastleURI(Diff $diff) {
    $uri = $this->getDiffProperty($diff, 'facebook:sandcastle_uri');
    if (!$uri) {
      $uri = $diff->getSandboxURL();
    }
    return $uri;
  }

  protected function getDiffProperty(Diff $diff, $property, $default = null) {
    $diff_id = $diff->getID();
    if (empty($this->diffProperties[$diff_id])) {
      $props = id(new DifferentialDiffProperty())
        ->loadAllWhere('diffID = %s', $diff_id);
      $dict = array_pull($props, 'getData', 'getName');
      $this->diffProperties[$diff_id] = $dict;
    }
    return idx($this->diffProperties[$diff_id], $property, $default);
  }

    $diff_table->appendChild(
      <tr>
        <td colspan="8" class="diff-differ-submit">
          <label>Whitespace Changes:</label>
          {id(<select name="whitespace" />)->setOptions(
            array(
              'ignore-all'      => 'Ignore All',
              'ignore-trailing' => 'Ignore Trailing',
              'show-all'        => 'Show All',
            ), $request->getStr('whitespace'))}{' '}
          <button type="submit">Show Diff</button>
        </td>
      </tr>);

    $load_ids = array_filter(array($old, $diff->getID()));

    $viewer_id = $this->getRequest()->getViewerContext()->getUserID();

    $raw_objects = queryfx_all(
      smc_get_db('cdb.differential', 'r'),
      'SELECT * FROM changeset WHERE changeset.diffID IN (%Ld)',
      $load_ids);

    $raw_objects = array_group($raw_objects, 'diffID');
    $objects = $raw_objects[$diff->getID()];

    if (!$objects) {
      $changesets = array();
    } else {
      $changesets = id(new DifferentialChangeset())->loadAllFromArray($objects);
    }


    $changesets = array_psort($changesets, 'getSortKey');


    $feedback = id(new DifferentialFeedback())->loadAllWithRevision($revision);
    $feedback = array_merge($implied_feedback, $feedback);

    $inline_comments = $this->loadInlineComments($feedback, $changesets);

    $diff_map = array();
    $diffs = array_psort($diffs, 'getID');
    foreach ($diffs as $diff) {
      $diff_map[$diff->getID()] = count($diff_map) + 1;
    }
    $visible_changesets = array_fill_keys($visible_changesets, true);
    $hidden_changesets = array();
    foreach ($changesets as $changeset) {
      $id = $changeset->getID();
      if (isset($visible_changesets[$id])) {
        continue;
      }
      $hidden_changesets[$id] = $diff_map[$changeset->getDiffID()];
    }

    $revision->loadRelationships();
    $ccs = $revision->getCCFBIDs();
    $reviewers = $revision->getReviewers();

    $actors = array_pull($feedback, 'getUserID');
    $actors[] = $revision->getOwnerID();

    $tasks = array();
    assoc_get_by_type(
      $revision->getFBID(),
      22284182462, // TODO: include issue, DIFFCAMP_TASK_ASSOC
      $start = null,
      $limit = null,
      $pending = true,
      $tasks);
    memcache_dispatch();
    $tasks = array_keys($tasks);

    $preparer = new Preparer();
      $fbids = array_merge_fast(
        array($actors, array($viewer_id), $reviewers, $ccs, $tasks),
        true);
      $handles = array();
      $handle_data = id(new ToolsHandleData($fbids, $handles))
        ->needNames()
        ->needAlternateNames()
        ->needAlternateIDs()
        ->needThumbnails();
      $preparer->waitFor($handle_data);
    $preparer->go();

    $revision->attachTaskHandles(array_select_keys($handles, $tasks));

    $inline_comments = array_group($inline_comments, 'getFeedbackID');

    $engine = new RemarkupEngine();
    $engine->enableFeature(RemarkupEngine::FEATURE_GUESS_IMAGES);
    $engine->enableFeature(RemarkupEngine::FEATURE_YOUTUBE);
    $engine->setCurrentSandcastle($this->getSandcastleURI($target_diff));
    $feed = array();
    foreach ($feedback as $comment) {
      $inlines = null;
      if (isset($inline_comments[$comment->getID()])) {
        $inlines = $inline_comments[$comment->getID()];
      }
      $feed[] =
        <differential:feedback
            feedback={$comment}
              handle={$handles[$comment->getUserID()]}
              engine={$engine}
              inline={$inlines}
          changesets={$changesets}
              hidden={$hidden_changesets} />;
    }

    $feed = $this->renderFeedbackList($feed, $feedback, $viewer_id);

    $fields = $this->getDetailFields($revision, $diff, $handles);
    $table = <table class="differential-revision-properties" />;
    foreach ($fields as $key => $value) {
      $table->appendChild(
        <tr>
          <th>{$key}:</th><td>{$value}</td>
        </tr>);
    }

    $quick_links = $this->getQuickLinks($revision);


    $info =
      <div class="differential-revision-information">
        <div class="differential-revision-actions">
          {$quick_links}
        </div>
        <div class="differential-revision-detail">
          <h1>{$revision->getName()}{$edit_link}</h1>
          {$table}
        </div>
      </div>;

    $actions = $this->getRevisionActions($revision);
    $revision_id = $revision->getID();

    $content = SavedCopy::loadData(
      $viewer_id,
      SavedCopy::Type_DifferentialRevisionFeedback,
      $revision->getFBID());


    $syntax_link =
      <a href={'http://www.intern.facebook.com/intern/wiki/index.php' .
               '/Articles/Remarkup_Syntax_Reference'}
         target="_blank"
         tabindex="4">Remarkup Reference</a>;


    $notice = null;
    if ($this->getRequest()->getBool('diff_changed')) {
      $notice =
        <tools:notice title="Revision Updated Recently">
          This revision was updated with a <strong>new diff</strong> while you
          were providing feedback. Your inline comments appear on the
          <strong>old diff</strong>.
        </tools:notice>;
    }

  protected function getQuickLinks(DifferentialRevision $revision) {

    $viewer_id = $this->getRequest()->getViewerContext()->getUserID();
    $viewer_is_owner = ($viewer_id == $revision->getOwnerID());
    $viewer_is_reviewer =
      ((array_search($viewer_id, $revision->getReviewers())) !== false);
    $viewer_is_cc =
      ((array_search($viewer_id, $revision->getCCFBIDs())) !== false);
    $status = $revision->getStatus();

    $links = array();

    if (!$viewer_is_owner && !$viewer_is_reviewer) {
      $action = $viewer_is_cc
        ? 'rem'
        : 'add';
      $revision_id = $revision->getID();
      $href = "/differential/subscribe/{$action}/{$revision_id}";
      $links[] = array(
        $viewer_is_cc ? 'subscribe-disabled' : 'subscribe-enabled',
        <a href={$href}>{$viewer_is_cc ? 'Unsubscribe' : 'Subscribe'}</a>,
      );
    } else {
      $links[] = array(
        'subscribe-disabled unavailable',
        <a>Automatically Subscribed</a>,
      );
    }

    $blast_uri = RedirectURI(
      '/intern/differential/?action=blast&fbid='.$revision->getFBID())
      ->setTier('intern');
    $links[] = array(
      'blast',
      <a href={$blast_uri}>Blast Revision</a>,
    );

    $blast_uri = RedirectURI(
      '/intern/differential/?action=tasks&fbid='.$revision->getFBID())
      ->setTier('intern');
    $links[] = array(
      'tasks',
      <a href={$blast_uri}>Edit Tasks</a>,
    );

    if ($viewer_is_owner && false) {
      $perflab_uri = RedirectURI(
        '/intern/differential/?action=perflab&fbid='.$revision->getFBID())
        ->setTier('intern');
      $links[] = array(
        'perflab',
        <a href={$perflab_uri}>Run in Perflab</a>,
      );
    }

    $engineering_repository_id = RepositoryRef::getByCallsign('E')->getID();
    $svn_revision = $revision->getSVNRevision();
    if ($status == DifferentialConstants::COMMITTED &&
        $svn_revision &&
        $revision->getRepositoryID() == $engineering_repository_id) {
      $href = '/intern/push/request.php?rev='.$svn_revision;
      $href = RedirectURI($href)->setTier('intern');
      $links[] = array(
        'merge',
        <a href={$href} id="ask_for_merge_link">Ask for Merge</a>,
      );
    }

    $links[] = array(
      'herald-transcript',
      <a href={"/herald/transcript/?fbid=".$revision->getFBID()}
        >Herald Transcripts</a>,
    );
    $links[] = array(
      'metamta-transcript',
      <a href={"/mail/?view=all&fbid=".$revision->getFBID()}
        >MetaMTA Transcripts</a>,
    );


    $list = <ul class="differential-actions" />;
    foreach ($links as $link) {
      list($class, $tag) = $link;
      $list->appendChild(<li class={$class}>{$tag}</li>);
    }

    return $list;
  }


  protected function renderDiffPropertyMoreLink(Diff $diff, $name) {
    $target = <div class="star-more"
                   style="display: none;">
                <div class="star-loading">Loading...</div>
              </div>;
    $meta = array(
      'target'  => $target->requireUniqueID(),
      'uri'     => '/differential/diffprop/'.$diff->getID().'/'.$name.'/',
    );
    $more =
      <span sigil="star-link-container">
        &middot;
        <a mustcapture="true"
                 sigil="star-more"
                  href="#"
                  meta={$meta}>Show Details</a>
      </span>;
    return <x:frag>{$more}{$target}</x:frag>;
  }



  protected function getRevisionStatusDisplay(DifferentialRevision $revision) {
    $viewer_id = $this->getRequest()->getViewerContext()->getUserID();
    $viewer_is_owner = ($viewer_id == $revision->getOwnerID());
    $status = $revision->getStatus();

    $more = null;
    switch ($status) {
      case DifferentialConstants::NEEDS_REVIEW:
        $message = 'Pending Review';
        break;
      case DifferentialConstants::NEEDS_REVISION:
        $message = 'Awaiting Revision';
        if ($viewer_is_owner) {
          $more = 'Make the requested changes and update the revision.';
        }
        break;
      case DifferentialConstants::ACCEPTED:
        $message = 'Ready for Commit';
        if ($viewer_is_owner) {
          $more =
            <x:frag>
              Run <tt>arc commit</tt> (svn) or <tt>arc amend</tt> (git) to
              proceed.
            </x:frag>;
        }
        break;
      case DifferentialConstants::COMMITTED:
        $message = 'Committed';
        $ref = $revision->getRevisionRef();
        $more = $ref
                ? (<a href={URI($ref->getDetailURL())}>
                     {$ref->getName()}
                   </a>)
                : null;

        $engineering_repository_id = RepositoryRef::getByCallsign('E')->getID();
        if ($revision->getSVNRevision() &&
            $revision->getRepositoryID() == $engineering_repository_id) {
          Javelin::initBehavior(
            'differential-revtracker-status',
            array(
              'uri' => '/differential/revtracker/'.$revision->getID().'/',
              'statusId' => 'revtracker_status',
              'mergeLinkId' => 'ask_for_merge_link',
            ));
        }
        break;
      case DifferentialConstants::ABANDONED:
        $message = 'Abandoned';
        break;
      default:
        throw new Exception("Unknown revision status.");
    }

    if ($more) {
      $message =
        <x:frag>
          <strong id="revtracker_status">{$message}</strong>
          &middot; {$more}
        </x:frag>;
    } else {
      $message = <strong id="revtracker_status">{$message}</strong>;
    }

    return $message;
  }

}
  protected function getDetailFields(
    DifferentialRevision $revision,
    Diff $diff,
    array $handles) {

    $fields = array();
    $fields['Revision Status'] = $this->getRevisionStatusDisplay($revision);


    $sandcastle = $this->getSandcastleURI($diff);
    if ($sandcastle) {
      $fields['Sandcastle'] = <a href={$sandcastle}>{$sandcastle}</a>;
    }


    $blame_rev = $revision->getSvnBlameRevision();
    if ($blame_rev) {
      if ($revision->getRepositoryRef() && is_numeric($blame_rev)) {
        $ref = new RevisionRef($revision->getRepositoryRef(), $blame_rev);
        $fields['Blame Revision'] =
          <a href={URI($ref->getDetailURL())}>
            {$ref->getName()}
          </a>;
      } else {
        $fields['Blame Revision'] = $blame_rev;
      }
    }

    $tasks = $revision->getTaskHandles();

    if ($tasks) {
      $links = array();
      foreach ($tasks as $task) {
        $links[] = <tools:handle handle={$task} link={true} />;
      }
      $fields['Tasks'] = array_implode(<br />, $links);
    }

    $bugzilla_id = $revision->getBugzillaID();
    if ($bugzilla_id) {
      $href = 'http://bugs.developers.facebook.com/show_bug.cgi?id='.
        $bugzilla_id;
      $fields['Bugzilla'] = <a href={$href}>{'#'.$bugzilla_id}</a>;
    }

    $fields['Apply Patch'] = <tt>arc patch --revision {$revision->getID()}</tt>;

    if ($diff->getParentRevisionID()) {
      $parent = id(new DifferentialRevision())->load(
        $diff->getParentRevisionID());
      if ($parent) {
        $fields['Depends On'] =
          <a href={$parent->getURI()}>
            D{$parent->getID()}: {$parent->getName()}
          </a>;
      }
    }

    Javelin::initBehavior('differential-star-more');
    if ($unit_details) {
      $fields['Unit Tests'] =
        <x:frag>
          {$fields['Unit Tests']}
          {$this->renderDiffPropertyMoreLink($diff, 'unit')}
        </x:frag>;
    }

    $platform_impact = $revision->getPlatformImpact();
    if ($platform_impact) {
      $fields['Platform Impact'] =
        <text linebreaks="true">{$platform_impact}</text>;
    }

    return $fields;
  }


*/
