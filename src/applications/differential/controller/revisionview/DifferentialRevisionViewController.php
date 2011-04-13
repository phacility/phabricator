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

    list($changesets, $vs_map) =
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
    foreach ($revision->getAttached() as $type => $phids) {
      foreach ($phids as $phid => $info) {
        $object_phids[] = $phid;
      }
    }
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

    $diff_properties = id(new DifferentialDiffProperty())->loadAllWhere(
      'diffID = %d AND name IN (%Ls)',
      $target->getID(),
      array(
        'arc:lint',
        'arc:unit',
      ));
    $diff_properties = mpull($diff_properties, 'getData', 'getName');

    $revision_detail = new DifferentialRevisionDetailView();
    $revision_detail->setRevision($revision);

    $custom_renderer_class = PhabricatorEnv::getEnvConfig(
      'differential.revision-custom-detail-renderer');
    if ($custom_renderer_class) {
      PhutilSymbolLoader::loadClass($custom_renderer_class);
      $custom_renderer =
        newv($custom_renderer_class, array());
    }

    $properties = $this->getRevisionProperties(
      $revision,
      $target,
      $handles,
      $diff_properties);
    if ($custom_renderer) {
      $properties = array_merge(
        $properties,
        $custom_renderer->generateProperties($revision, $target));
    }

    $revision_detail->setProperties($properties);

    $actions = $this->getRevisionActions($revision);
    if ($custom_renderer) {
      $actions = array_merge(
        $actions,
        $custom_renderer->generateActionLinks($revision, $target));
    }

    $revision_detail->setActions($actions);

    $revision_detail->setUser($user);

    $comment_view = new DifferentialRevisionCommentListView();
    $comment_view->setComments($comments);
    $comment_view->setHandles($handles);
    $comment_view->setInlineComments($inlines);
    $comment_view->setChangesets($all_changesets);
    $comment_view->setUser($user);

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
    array $handles,
    array $diff_properties) {

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
    $ldata = idx($diff_properties, 'arc:lint');
    $ltail = null;
    if ($ldata) {
      $ldata = igroup($ldata, 'path');
      $lint_messages = array();
      foreach ($ldata as $path => $messages) {
        $message_markup = array();
        foreach ($messages as $message) {
          $path = idx($message, 'path');
          $line = idx($message, 'line');

          $code = idx($message, 'code');
          $severity = idx($message, 'severity');

          $name = idx($message, 'name');
          $description = idx($message, 'description');

          $message_markup[] =
            '<li>'.
              '<span class="lint-severity-'.phutil_escape_html($severity).'">'.
                phutil_escape_html(ucwords($severity)).
              '</span>'.
              ' '.
              '('.phutil_escape_html($code).') '.
              phutil_escape_html($name).
              ' at line '.phutil_escape_html($line).
              '<p>'.phutil_escape_html($description).'</p>'.
            '</li>';
        }
        $lint_messages[] =
          '<li class="lint-file-block">'.
            'Lint for <strong>'.phutil_escape_html($path).'</strong>'.
            '<ul>'.implode("\n", $message_markup).'</ul>'.
          '</li>';
      }
      $ltail =
        '<div class="differential-lint-block">'.
          '<ul>'.
            implode("\n", $lint_messages).
          '</ul>'.
        '</div>';
    }

    $properties['Lint'] = $lstar.' '.$lmsg.$ltail;

    $ustar = DifferentialRevisionUpdateHistoryView::renderDiffUnitStar($diff);
    $umsg = DifferentialRevisionUpdateHistoryView::getDiffUnitMessage($diff);

    $udata = idx($diff_properties, 'arc:unit');
    $utail = null;
    if ($udata) {
      $unit_messages = array();
      foreach ($udata as $test) {
        $name = phutil_escape_html(idx($test, 'name'));
        $result = phutil_escape_html(idx($test, 'result'));
        $userdata = phutil_escape_html(idx($test, 'userdata'));
        if (strlen($userdata) > 256) {
          $userdata = substr($userdata, 0, 256).'...';
        }
        $userdata = str_replace("\n", '<br />', $userdata);
        $unit_messages[] =
          '<tr>'.
            '<th>'.$name.'</th>'.
            '<th class="unit-test-result">'.
              '<div class="result-'.$result.'">'.
                strtoupper($result).
              '</div>'.
            '</th>'.
            '<td>'.$userdata.'</td>'.
          '</tr>';
      }

      $utail =
        '<div class="differential-unit-block">'.
          '<table class="differential-unit-table">'.
            implode("\n", $unit_messages).
          '</table>'.
        '</div>';
    }

    $properties['Unit'] = $ustar.' '.$umsg.$utail;

    if (PhabricatorEnv::getEnvConfig('maniphest.enabled')) {
      $tasks = $revision->getAttachedPHIDs(
        PhabricatorPHIDConstants::PHID_TYPE_TASK);
      if ($tasks) {
        $links = array();
        foreach ($tasks as $task_phid) {
          $links[] = $handles[$task_phid]->renderLink();
        }
        $properties['Maniphest Tasks'] = implode('<br />', $links);
      }
    }

    $commit_phids = $revision->getCommitPHIDs();
    if ($commit_phids) {
      $links = array();
      foreach ($commit_phids as $commit_phid) {
        $links[] = $handles[$commit_phid]->renderLink();
      }
      $properties['Commits'] = implode('<br />', $links);
    }

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

    if (PhabricatorEnv::getEnvConfig('maniphest.enabled')) {
      $links[] = array(
        'class' => 'attach-maniphest',
        'name'  => 'Edit Maniphest Tasks',
        'href'  => "/differential/attach/{$revision_id}/TASK/",
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
    $viewer_is_reviewer = in_array($viewer_phid, $revision->getReviewers());

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


    $engine = new RemarkupEngine();
    $engine->enableFeature(RemarkupEngine::FEATURE_GUESS_IMAGES);
    $engine->enableFeature(RemarkupEngine::FEATURE_YOUTUBE);
    $engine->setCurrentSandcastle($this->getSandcastleURI($target_diff));

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
