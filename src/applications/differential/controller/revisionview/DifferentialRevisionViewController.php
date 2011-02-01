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

    $target = end($diffs);

    $changesets = $target->loadChangesets();

    $comments = $revision->loadComments();
    $comments = array_merge(
      $this->getImplicitComments($revision),
      $comments);

    $object_phids = array_merge(
      $revision->getReviewers(),
      $revision->getCCPHIDs(),
      array(
        $revision->getAuthorPHID(),
        $request->getUser()->getPHID(),
      ),
      mpull($comments, 'getAuthorPHID'));

    $handles = id(new PhabricatorObjectHandleData($object_phids))
      ->loadHandles();

    $revision_detail = new DifferentialRevisionDetailView();
    $revision_detail->setRevision($revision);

    $properties = $this->getRevisionProperties($revision, $target, $handles);
    $revision_detail->setProperties($properties);

    $actions = $this->getRevisionActions($revision);
    $revision_detail->setActions($actions);

    $comment_view = new DifferentialRevisionCommentListView();
    $comment_view->setComments($comments);
    $comment_view->setHandles($handles);

    $diff_history = new DifferentialRevisionUpdateHistoryView();
    $diff_history->setDiffs($diffs);

    $toc_view = new DifferentialDiffTableOfContentsView();
    $toc_view->setChangesets($changesets);

    $changeset_view = new DifferentialChangesetListView();
    $changeset_view->setChangesets($changesets);
    $changeset_view->setEditable(true);
    $changeset_view->setRevision($revision);

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

    $path = $diff->getSourcePath();
    if ($path) {
      $branch = $diff->getBranch() ? ' (' . $diff->getBranch() . ')' : '';
      $host = $diff->getSourceMachine();
      if ($host) {
        $host .= ':';
      }
      $properties['Path'] = phutil_escape_html("{$host}{$path} {$branch}");
    }


    $properties['Lint'] = 'TODO';
    $properties['Unit'] = 'TODO';

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



/*
// TODO
//    $sandcastle = $this->getSandcastleURI($diff);
//    if ($sandcastle) {
//      $fields['Sandcastle'] = <a href={$sandcastle}>{$sandcastle}</a>;
//    }

    $path = $diff->getSourcePath();
    if ($path) {
      $host = $diff->getSourceMachine();
      $branch = $diff->getGitBranch() ? ' (' . $diff->getGitBranch() . ')' : '';

      if ($host) {
// TODO
//        $user = $handles[$this->getRequest()->getViewerContext()->getUserID()]
//          ->getName();
        $user = 'TODO';
        $fields['Path'] =
          <x:frag>
            <a href={"ssh://{$user}@{$host}"}>{$host}</a>:{$path}{$branch}
          </x:frag>;
      } else {
        $fields['Path'] = $path;
      }
    }

    $reviewer_links = array();
    foreach ($revision->getReviewers() as $reviewer) {
      $reviewer_links[] = <tools:handle handle={$handles[$reviewer]}
                                          link={true} />;
    }
    if ($reviewer_links) {
      $fields['Reviewers'] = array_implode(', ', $reviewer_links);
    } else {
      $fields['Reviewers'] = <em>None</em>;
    }

    $ccs = $revision->getCCFBIDs();
    if ($ccs) {
      $links = array();
      foreach ($ccs as $cc) {
        $links[] = <tools:handle handle={$handles[$cc]}
                                   link={true} />;
      }
      $fields['CCs'] = array_implode(', ', $links);
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

    $star = <span class="star">{"\xE2\x98\x85"}</span>;

    Javelin::initBehavior('differential-star-more');

    switch ($diff->getLinted()) {
      case Diff::LINT_FAIL:
        $more = $this->renderDiffPropertyMoreLink($diff, 'lint');
        $fields['Lint'] =
          <x:frag>
            <span class="star-warn">{$star} Lint Failures</span>
            {$more}
          </x:frag>;
        break;
      case Diff::LINT_WARNINGS:
        $more = $this->renderDiffPropertyMoreLink($diff, 'lint');
        $fields['Lint'] =
          <x:frag>
            <span class="star-warn">{$star} Lint Warnings</span>
            {$more}
          </x:frag>;
        break;
      case Diff::LINT_OKAY:
        $fields['Lint'] =
          <span class="star-okay">{$star} Lint Free</span>;
        break;
      default:
      case Diff::LINT_NO:
        $fields['Lint'] =
          <span class="star-none">{$star} Not Linted</span>;
        break;
    }

    $unit_details = false;
    switch ($diff->getUnitTested()) {
      case Diff::UNIT_FAIL:
        $fields['Unit Tests'] =
            <span class="star-warn">{$star} Unit Test Failures</span>;
        $unit_details = true;
        break;
      case Diff::UNIT_WARN:
        $fields['Unit Tests'] =
            <span class="star-warn">{$star} Unit Test Warnings</span>;
        $unit_details = true;
        break;
      case Diff::UNIT_OKAY:
        $fields['Unit Tests'] =
          <span class="star-okay">{$star} Unit Tests Passed</span>;
        $unit_details = true;
        break;
      case Diff::UNIT_NO_TESTS:
        $fields['Unit Tests'] =
          <span class="star-none">{$star} No Test Coverage</span>;
        break;
      case Diff::UNIT_NO:
      default:
        $fields['Unit Tests'] =
          <span class="star-none">{$star} Not Unit Tested</span>;
        break;
    }

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

  public function process() {
    $uri = $this->getRequest()->getPath();
    if (starts_with($uri, '/d')) {
      return <alite:redirect uri={strtoupper($uri)}/>;
    }

    $revision = id(new DifferentialRevision())->load($this->revisionID);
    if (!$revision) {
      throw new Exception("Bad revision ID.");
    }

    $diffs = id(new Diff())->loadAllWhere(
      'revisionID = %d',
      $revision->getID());
    $diffs = array_psort($diffs, 'getID');

    $request = $this->getRequest();
    $new = $request->getInt('new');
    $old = $request->getInt('old');

    if (($new || $old) && $new <= $old) {
      throw new Exception(
        "You can only view the diff of an older update relative to a newer ".
        "update.");
    }

    if ($new && empty($diffs[$new])) {
      throw new Exception(
        "The 'new' diff does not exist.");
    } else if ($new) {
      $diff = $diffs[$new];
    } else {
      $diff = end($diffs);
      if (!$diff) {
        throw new Exception("No diff attached to this revision?");
      }
      $new = $diff->getID();
    }

    $target_diff = $diff;

    if ($old && empty($diffs[$old])) {
      throw new Exception(
        "The 'old' diff does not exist.");
    }

    $rows = array(array('Base', '', true, false, null,
      $diff->getSourceControlBaseRevision()
        ? $diff->getSourceControlBaseRevision()
        : <em>Master</em>));
    $idx = 0;
    foreach ($diffs as $cdiff) {
      $rows[] = array(
        'Diff '.(++$idx),
        $cdiff->getID(),
        $cdiff->getID() != max(array_pull($diffs, 'getID')),
        true,
        $cdiff->getDateCreated(),
        $cdiff->getDescription()
          ? $cdiff->getDescription()
          : <em>No description available.</em>,
        $cdiff->getUnitTested(),
        $cdiff->getLinted());
    }

    $diff_table =
      <table class="differential-diff-differ">
        <tr>
          <th>Diff</th>
          <th>Diff ID</th>
          <th>Description</th>
          <th>Age</th>
          <th>Lint</th>
          <th>Unit</th>
        </tr>
      </table>;
    $ii = 0;

    $old_ids = array();
    foreach ($rows as $row) {
      $xold = null;
      if ($row[2]) {
        $lradio = <input name="old" value={$row[1]} type="radio"
          disabled={$row[1] >= $new}
          checked={$old == $row[1]} />;
        if ($old == $row[1]) {
          $xold = 'old-now';
        }
        $old_ids[] = $lradio->requireUniqueID();
      } else {
        $lradio = null;
      }
      $xnew = null;
      if ($row[3]) {
        $rradio = <input name="new" value={$row[1]} type="radio"
          sigil="new-radio"
          checked={$new == $row[1]} />;
        if ($new == $row[1]) {
          $xnew = 'new-now';
        }
      } else {
        $rradio = null;
      }

      if ($row[3]) {
        $unit_star = 'star-none';
        switch ($row[6]) {
          case Diff::UNIT_FAIL:
          case Diff::UNIT_WARN: $unit_star = 'star-warn'; break;
          case Diff::UNIT_OKAY: $unit_star = 'star-okay'; break;
        }

        $lint_star = 'star-none';
        switch ($row[7]) {
          case Diff::LINT_FAIL:
          case Diff::LINT_WARNINGS: $lint_star = 'star-warn'; break;
          case Diff::LINT_OKAY:     $lint_star = 'star-okay'; break;
        }

        $star = "\xE2\x98\x85";

        $unit_star =
          <span class={$unit_star}>
            <span class="star">{$star}</span>
          </span>;

        $lint_star =
          <span class={$lint_star}>
            <span class="star">{$star}</span>
          </span>;
      } else {
        $unit_star = null;
        $lint_star = null;
      }

      $diff_table->appendChild(
        <tr class={++$ii % 2 ? 'alt' : null}>
          <td class="name">{$row[0]}</td>
          <td class="diffid">{$row[1]}</td>
          <td class="desc">{$row[5]}</td>
          <td class="age">{$row[4] ? ago(time() - $row[4]) : null}</td>
          <td class="star">{$lint_star}</td>
          <td class="star">{$unit_star}</td>
          <td class={"old {$xold}"}>{$lradio}</td>
          <td class={"new {$xnew}"}>{$rradio}</td>
        </tr>);
    }

    Javelin::initBehavior('differential-diff-radios', array(
      'radios' => $old_ids,
    ));

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

    $diff_table =
      <div class="differential-table-of-contents">
        <h1>Revision Update History</h1>
        <form action={URI::getRequestURI()} method="get">
          {$diff_table}
        </form>
      </div>;


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

    $against_warn = null;
    $against_map = array();
    $visible_changesets = array();
    if ($old) {
      $old_diff = $diffs[$old];
      $new_diff = $diff;
      $old_path = $old_diff->getSourcePath();
      $new_path = $new_diff->getSourcePath();

      $old_prefix = null;
      $new_prefix = null;
      if ((strlen($old_path) < strlen($new_path)) &&
          (!strncmp($old_path, $new_path, strlen($old_path)))) {
        $old_prefix = substr($new_path, strlen($old_path));
      }
      if ((strlen($new_path) < strlen($old_path)) &&
          (!strncmp($old_path, $new_path, strlen($new_path)))) {
        $new_prefix = substr($old_path, strlen($new_path));
      }

      $old_changesets = id(new DifferentialChangeset())
        ->loadAllFromArray($raw_objects[$old]);
      $old_changesets = array_pull($old_changesets, null, 'getFilename');
      if ($new_prefix) {
        $rekeyed_map = array();
        foreach ($old_changesets as $key => $value) {
          $rekeyed_map[$new_prefix.$key] = $value;
        }
        $old_changesets = $rekeyed_map;
      }

      foreach ($changesets as $key => $changeset) {
        $file = $old_prefix.$changeset->getFilename();
        if (isset($old_changesets[$file])) {
          $checksum = $changeset->getChecksum();
          if ($checksum !== null &&
              $checksum == $old_changesets[$file]->getChecksum()) {
            unset($changesets[$key]);
            unset($old_changesets[$file]);
          } else {
            $against_map[$changeset->getID()] = $old_changesets[$file]->getID();
            unset($old_changesets[$file]);
          }
        }
      }

      foreach ($old_changesets as $changeset) {
        $changesets[$changeset->getID()] = $changeset;
        $against_map[$changeset->getID()] = -1;
      }

      $against_warn =
        <tools:notice title="NOTE - Diff of Diffs">
          You are viewing a synthetic diff between two previous diffs in this
          revision. You can not add new inline comments (for now).
        </tools:notice>;
    } else {
      $visible_changesets = array_pull($changesets, 'getID');
    }

    $changesets = array_psort($changesets, 'getSortKey');
    $all_changesets = $changesets;

    $warning = null;
    $limit = 100;
    if (count($changesets) > $limit && !$this->getRequest()->getStr('large')) {
      $count = number_format(count($changesets));
      $warning =
        <tools:notice title="Very Large Diff">
          This diff is extremely large and affects {$count} files. Only the
          first {number_format($limit)} files are shown.
          <strong>
            <a href={$revision->getURI().'?large=true'}>Show All Files</a>
          </strong>
        </tools:notice>;
      $changesets = array_slice($changesets, 0, $limit);
      if (!$old) {
        $visible_changesets = array_pull($changesets, 'getID');
      }
    }

    $detail_view =
      <differential:changeset-detail-view
        changesets={$changesets}
          revision={$revision}
           against={$against_map}
              edit={empty($against_map)}
        whitespace={$request->getStr('whitespace')} />;

    $table_of_contents =
      <differential:changeset-table-of-contents
        changesets={$all_changesets} />;

    $implied_feedback = array();
    foreach (array(
      'summarize'   => $revision->getSummary(),
      'testplan'    => $revision->getTestPlan(),
      'annotate'    => $revision->getNotes(),
    ) as $type => $text) {
      if (!strlen($text)) {
        continue;
      }
      $implied_feedback[] = id(new DifferentialFeedback())
        ->setUserID($revision->getOwnerID())
        ->setAction($type)
        ->setDateCreated($revision->getDateCreated())
        ->setContent($text);
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

    $edit_link = null;
    if ($revision->getOwnerID() == $viewer_id) {
      $edit_link = '/differential/revision/edit/'.$revision->getID().'/';
      $edit_link =
        <x:frag>
          {' '}(<a href={$edit_link}>Edit Revision</a>)
        </x:frag>;
    }

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

    Javelin::initBehavior(
      'differential-feedback-preview',
      array(
        'uri'     => '/differential/preview/'.$revision->getFBID().'/',
        'preview' => 'overall-feedback-preview',
        'action'  => 'feedback-action',
        'content' => 'feedback-content',
      ));

    Javelin::initBehavior(
      'differential-inline-comment-preview',
      array(
        'uri' => '/differential/inline-preview/'.$revision_id.'/'.$new.'/',
        'preview' => 'inline-comment-preview',
      ));

    $content = SavedCopy::loadData(
      $viewer_id,
      SavedCopy::Type_DifferentialRevisionFeedback,
      $revision->getFBID());


    $inline_comment_container =
        <div id="inline-comment-preview"><p>Loading...</p></div>;

    $feedback = id(new DifferentialFeedback())
      ->setAction('none')
      ->setUserID($viewer_id)
      ->setContent($content);

    $preview =
      <div class="differential-feedback differential-feedback-preview">
        <div id="overall-feedback-preview">
          <differential:feedback
            feedback={$feedback}
              engine={$engine}
             preview={true}
              handle={$handles[$viewer_id]} />
        </div>
        {$inline_comment_container}
      </div>;

    $syntax_link =
      <a href={'http://www.intern.facebook.com/intern/wiki/index.php' .
               '/Articles/Remarkup_Syntax_Reference'}
         target="_blank"
         tabindex="4">Remarkup Reference</a>;

    Javelin::initBehavior(
      'differential-add-reviewers',
      array(
        'src'       => redirect_str('/datasource/employee/', 'tools'),
        'tokenizer' => 'reviewer-tokenizer',
        'select'    => 'feedback-action',
        'row'       => 'reviewer-tokenizer-row',
      ));

    $feedback_form =
      <x:frag>
        <div class="differential-feedback-form">
          <tools:form
            method="post"
            action={"/differential/revision/feedback/{$revision_id}/"}>
            <h1>Provide Feedback</h1>
            <tools:fieldset>
              <tools:control type="select" label="Action">
                {id(<select name="action" id="feedback-action"
                      tabindex="1" />)
                  ->setOptions($actions)}
              </tools:control>
              <tools:control type="text" label="Reviewers"
                style="display: none;"
                id="reviewer-tokenizer-row">
                <javelin:tokenizer-template
                  id="reviewer-tokenizer"
                  name="reviewers" />
              </tools:control>
              <tools:control type="textarea" label="Feedback"
                caption={$syntax_link}>
                <tools:droppable-textarea id="feedback-content" name="feedback"
                  tabindex="2">
                  {$content}
                </tools:droppable-textarea>
              </tools:control>
              <tools:control type="submit">
                <button type="submit"
                  tabindex="3">Clowncopterize</button>
              </tools:control>
            </tools:fieldset>
          </tools:form>
        </div>
        {$preview}
      </x:frag>;

    $notice = null;
    if ($this->getRequest()->getBool('diff_changed')) {
      $notice =
        <tools:notice title="Revision Updated Recently">
          This revision was updated with a <strong>new diff</strong> while you
          were providing feedback. Your inline comments appear on the
          <strong>old diff</strong>.
        </tools:notice>;
    }

    return
      <differential:standard-page title={$revision->getName()}>
        <div class="differential-primary-pane">
          {$warning}
          {$notice}
          {$info}
          <div class="differential-feedback">
            {$feed}
          </div>
          {$diff_table}
          {$table_of_contents}
          {$against_warn}
          {$detail_view}
          {$feedback_form}
        </div>
      </differential:standard-page>;
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

  protected function getDetailFields(
    DifferentialRevision $revision,
    Diff $diff,
    array $handles) {

    $fields = array();
    $fields['Revision Status'] = $this->getRevisionStatusDisplay($revision);

    $author = $revision->getOwnerID();
    $fields['Author'] = <tools:handle handle={$handles[$author]}
                                        link={true} />;

    $sandcastle = $this->getSandcastleURI($diff);
    if ($sandcastle) {
      $fields['Sandcastle'] = <a href={$sandcastle}>{$sandcastle}</a>;
    }

    $path = $diff->getSourcePath();
    if ($path) {
      $host = $diff->getSourceMachine();
      $branch = $diff->getGitBranch() ? ' (' . $diff->getGitBranch() . ')' : '';

      if ($host) {
        $user = $handles[$this->getRequest()->getViewerContext()->getUserID()]
          ->getName();
        $fields['Path'] =
          <x:frag>
            <a href={"ssh://{$user}@{$host}"}>{$host}</a>:{$path}{$branch}
          </x:frag>;
      } else {
        $fields['Path'] = $path;
      }
    }

    $reviewer_links = array();
    foreach ($revision->getReviewers() as $reviewer) {
      $reviewer_links[] = <tools:handle handle={$handles[$reviewer]}
                                          link={true} />;
    }
    if ($reviewer_links) {
      $fields['Reviewers'] = array_implode(', ', $reviewer_links);
    } else {
      $fields['Reviewers'] = <em>None</em>;
    }

    $ccs = $revision->getCCFBIDs();
    if ($ccs) {
      $links = array();
      foreach ($ccs as $cc) {
        $links[] = <tools:handle handle={$handles[$cc]}
                                   link={true} />;
      }
      $fields['CCs'] = array_implode(', ', $links);
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

    $star = <span class="star">{"\xE2\x98\x85"}</span>;

    Javelin::initBehavior('differential-star-more');

    switch ($diff->getLinted()) {
      case Diff::LINT_FAIL:
        $more = $this->renderDiffPropertyMoreLink($diff, 'lint');
        $fields['Lint'] =
          <x:frag>
            <span class="star-warn">{$star} Lint Failures</span>
            {$more}
          </x:frag>;
        break;
      case Diff::LINT_WARNINGS:
        $more = $this->renderDiffPropertyMoreLink($diff, 'lint');
        $fields['Lint'] =
          <x:frag>
            <span class="star-warn">{$star} Lint Warnings</span>
            {$more}
          </x:frag>;
        break;
      case Diff::LINT_OKAY:
        $fields['Lint'] =
          <span class="star-okay">{$star} Lint Free</span>;
        break;
      default:
      case Diff::LINT_NO:
        $fields['Lint'] =
          <span class="star-none">{$star} Not Linted</span>;
        break;
    }

    $unit_details = false;
    switch ($diff->getUnitTested()) {
      case Diff::UNIT_FAIL:
        $fields['Unit Tests'] =
            <span class="star-warn">{$star} Unit Test Failures</span>;
        $unit_details = true;
        break;
      case Diff::UNIT_WARN:
        $fields['Unit Tests'] =
            <span class="star-warn">{$star} Unit Test Warnings</span>;
        $unit_details = true;
        break;
      case Diff::UNIT_OKAY:
        $fields['Unit Tests'] =
          <span class="star-okay">{$star} Unit Tests Passed</span>;
        $unit_details = true;
        break;
      case Diff::UNIT_NO_TESTS:
        $fields['Unit Tests'] =
          <span class="star-none">{$star} No Test Coverage</span>;
        break;
      case Diff::UNIT_NO:
      default:
        $fields['Unit Tests'] =
          <span class="star-none">{$star} Not Unit Tested</span>;
        break;
    }

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



  protected function loadInlineComments(array $feedback, array &$changesets) {

    $inline_comments = array();
    $feedback_ids = array_filter(array_pull($feedback, 'getID'));
    if (!$feedback_ids) {
      return $inline_comments;
    }

    $inline_comments = id(new DifferentialInlineComment())
      ->loadAllWhere('feedbackID in (%Ld)', $feedback_ids);

    $load_changesets = array();
    $load_hunks = array();
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
        ->loadAllWithIDs($changeset_ids);
    }

    if ($more_changesets) {
      $changesets += $more_changesets;
      $changesets = array_psort($changesets, 'getSortKey');
    }

    return $inline_comments;
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

  protected function renderFeedbackList(array $xhp, array $obj, $viewer_id) {

    // Use magical heuristics to try to hide older comments.

    $obj = array_reverse($obj);
    $obj = array_values($obj);
    $xhp = array_reverse($xhp);
    $xhp = array_values($xhp);

    $last_comment = null;
    foreach ($obj as $position => $feedback) {
      if ($feedback->getUserID() == $viewer_id) {
        if ($last_comment === null) {
          $last_comment = $position;
        } else if ($last_comment == $position - 1) {
          // If you made consecuitive comments, show them all. This is a spaz
          // rule for epriestley comments.
          $last_comment = $position;
        }
      }
    }

    $header = array();

    $hide = array();
    if ($last_comment !== null) {
      foreach ($obj as $position => $feedback) {
        $action = $feedback->getAction();
        if ($action == 'testplan' || $action == 'summarize') {
          // Always show summary and test plan.
          $header[] = $xhp[$position];
          unset($xhp[$position]);
          continue;
        }

        if ($position <= $last_comment) {
          // Always show comments after your last comment.
          continue;
        }

        if ($position < 3) {
          // Always show the most recent 3 comments.
          continue;
        }

        // Hide everything else.
        $hide[] = $position;
      }
    }

    if (count($hide) <= 3) {
      // Don't hide if there's not much to hide.
      $hide = array();
    }

    $header = array_reverse($header);

    $hidden = array_select_keys($xhp, $hide);
    $visible = array_diff_key($xhp, $hidden);

    $visible = array_reverse($visible);
    $hidden  = array_reverse($hidden);

    if ($hidden) {
      Javelin::initBehavior(
        'differential-show-all-feedback',
        array(
          'markup' => id(<x:frag>{$hidden}</x:frag>)->toString(),
        ));
      $hidden =
        <div sigil="all-feedback-container">
          <div class="older-replies-are-hidden">
            {number_format(count($hidden))} older replies are hidden.
            <a href="#" sigil="show-all-feedback"
              mustcapture="true">Show all feedback.</a>
          </div>
        </div>;
    } else {
      $hidden = null;
    }

    return
      <x:frag>
        {$header}
        {$hidden}
        {$visible}
      </x:frag>;
  }

}
  protected function getDetailFields(
    DifferentialRevision $revision,
    Diff $diff,
    array $handles) {

    $fields = array();
    $fields['Revision Status'] = $this->getRevisionStatusDisplay($revision);

    $author = $revision->getOwnerID();
    $fields['Author'] = <tools:handle handle={$handles[$author]}
                                        link={true} />;

    $sandcastle = $this->getSandcastleURI($diff);
    if ($sandcastle) {
      $fields['Sandcastle'] = <a href={$sandcastle}>{$sandcastle}</a>;
    }

    $path = $diff->getSourcePath();
    if ($path) {
      $host = $diff->getSourceMachine();
      $branch = $diff->getGitBranch() ? ' (' . $diff->getGitBranch() . ')' : '';

      if ($host) {
        $user = $handles[$this->getRequest()->getViewerContext()->getUserID()]
          ->getName();
        $fields['Path'] =
          <x:frag>
            <a href={"ssh://{$user}@{$host}"}>{$host}</a>:{$path}{$branch}
          </x:frag>;
      } else {
        $fields['Path'] = $path;
      }
    }

    $reviewer_links = array();
    foreach ($revision->getReviewers() as $reviewer) {
      $reviewer_links[] = <tools:handle handle={$handles[$reviewer]}
                                          link={true} />;
    }
    if ($reviewer_links) {
      $fields['Reviewers'] = array_implode(', ', $reviewer_links);
    } else {
      $fields['Reviewers'] = <em>None</em>;
    }

    $ccs = $revision->getCCFBIDs();
    if ($ccs) {
      $links = array();
      foreach ($ccs as $cc) {
        $links[] = <tools:handle handle={$handles[$cc]}
                                   link={true} />;
      }
      $fields['CCs'] = array_implode(', ', $links);
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

    $star = <span class="star">{"\xE2\x98\x85"}</span>;

    Javelin::initBehavior('differential-star-more');

    switch ($diff->getLinted()) {
      case Diff::LINT_FAIL:
        $more = $this->renderDiffPropertyMoreLink($diff, 'lint');
        $fields['Lint'] =
          <x:frag>
            <span class="star-warn">{$star} Lint Failures</span>
            {$more}
          </x:frag>;
        break;
      case Diff::LINT_WARNINGS:
        $more = $this->renderDiffPropertyMoreLink($diff, 'lint');
        $fields['Lint'] =
          <x:frag>
            <span class="star-warn">{$star} Lint Warnings</span>
            {$more}
          </x:frag>;
        break;
      case Diff::LINT_OKAY:
        $fields['Lint'] =
          <span class="star-okay">{$star} Lint Free</span>;
        break;
      default:
      case Diff::LINT_NO:
        $fields['Lint'] =
          <span class="star-none">{$star} Not Linted</span>;
        break;
    }

    $unit_details = false;
    switch ($diff->getUnitTested()) {
      case Diff::UNIT_FAIL:
        $fields['Unit Tests'] =
            <span class="star-warn">{$star} Unit Test Failures</span>;
        $unit_details = true;
        break;
      case Diff::UNIT_WARN:
        $fields['Unit Tests'] =
            <span class="star-warn">{$star} Unit Test Warnings</span>;
        $unit_details = true;
        break;
      case Diff::UNIT_OKAY:
        $fields['Unit Tests'] =
          <span class="star-okay">{$star} Unit Tests Passed</span>;
        $unit_details = true;
        break;
      case Diff::UNIT_NO_TESTS:
        $fields['Unit Tests'] =
          <span class="star-none">{$star} No Test Coverage</span>;
        break;
      case Diff::UNIT_NO:
      default:
        $fields['Unit Tests'] =
          <span class="star-none">{$star} Not Unit Tested</span>;
        break;
    }

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
