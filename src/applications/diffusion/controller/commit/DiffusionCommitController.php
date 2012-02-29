<?php

/*
 * Copyright 2012 Facebook, Inc.
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

class DiffusionCommitController extends DiffusionController {

  const CHANGES_LIMIT = 100;

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();
    $request = $this->getRequest();
    $user = $request->getUser();

    $callsign = $drequest->getRepository()->getCallsign();

    $content = array();
    $content[] = $this->buildCrumbs(array(
      'commit' => true,
    ));

    $detail_panel = new AphrontPanelView();

    $repository = $drequest->getRepository();
    $commit = $drequest->loadCommit();

    if (!$commit) {
      // TODO: Make more user-friendly.
      throw new Exception('This commit has not parsed yet.');
    }

    $commit_data = $drequest->loadCommitData();

    $is_foreign = $commit_data->getCommitDetail('foreign-svn-stub');
    if ($is_foreign) {
      $subpath = $commit_data->getCommitDetail('svn-subpath');

      $error_panel = new AphrontErrorView();
      $error_panel->setWidth(AphrontErrorView::WIDTH_WIDE);
      $error_panel->setTitle('Commit Not Tracked');
      $error_panel->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $error_panel->appendChild(
        "This Diffusion repository is configured to track only one ".
        "subdirectory of the entire Subversion repository, and this commit ".
        "didn't affect the tracked subdirectory ('".
        phutil_escape_html($subpath)."'), so no information is available.");
      $content[] = $error_panel;
    } else {
      $engine = PhabricatorMarkupEngine::newDifferentialMarkupEngine();

      require_celerity_resource('diffusion-commit-view-css');
      require_celerity_resource('phabricator-remarkup-css');

      $property_table = $this->renderPropertyTable($commit, $commit_data);

      $detail_panel->appendChild(
        '<div class="diffusion-commit-view">'.
          '<div class="diffusion-commit-dateline">'.
            'r'.$callsign.$commit->getCommitIdentifier().
            ' &middot; '.
            phabricator_datetime($commit->getEpoch(), $user).
          '</div>'.
          '<h1>Revision Detail</h1>'.
          '<div class="diffusion-commit-details">'.
            $property_table.
            '<hr />'.
            '<div class="diffusion-commit-message phabricator-remarkup">'.
              $engine->markupText($commit_data->getCommitMessage()).
            '</div>'.
          '</div>'.
        '</div>');

      $content[] = $detail_panel;
    }

    $content[] = $this->buildAuditTable($commit);
    $content[] = $this->buildComments($commit);

    $change_query = DiffusionPathChangeQuery::newFromDiffusionRequest(
      $drequest);
    $changes = $change_query->loadChanges();

    $original_changes_count = count($changes);
    if ($request->getStr('show_all') !== 'true' &&
        $original_changes_count > self::CHANGES_LIMIT) {
      $changes = array_slice($changes, 0, self::CHANGES_LIMIT);
    }

    $change_table = new DiffusionCommitChangeTableView();
    $change_table->setDiffusionRequest($drequest);
    $change_table->setPathChanges($changes);

    $count = count($changes);

    $bad_commit = null;
    if ($count == 0) {
      $bad_commit = queryfx_one(
        id(new PhabricatorRepository())->establishConnection('r'),
        'SELECT * FROM %T WHERE fullCommitName = %s',
        PhabricatorRepository::TABLE_BADCOMMIT,
        'r'.$callsign.$commit->getCommitIdentifier());
    }

    if ($bad_commit) {
      $error_panel = new AphrontErrorView();
      $error_panel->setWidth(AphrontErrorView::WIDTH_WIDE);
      $error_panel->setTitle('Bad Commit');
      $error_panel->appendChild(
        phutil_escape_html($bad_commit['description']));

      $content[] = $error_panel;
    } else if ($is_foreign) {
      // Don't render anything else.
    } else if (!count($changes)) {
      $no_changes = new AphrontErrorView();
      $no_changes->setWidth(AphrontErrorView::WIDTH_WIDE);
      $no_changes->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $no_changes->setTitle('Not Yet Parsed');
      // TODO: This can also happen with weird SVN changes that don't do
      // anything (or only alter properties?), although the real no-changes case
      // is extremely rare and might be impossible to produce organically. We
      // should probably write some kind of "Nothing Happened!" change into the
      // DB once we parse these changes so we can distinguish between
      // "not parsed yet" and "no changes".
      $no_changes->appendChild(
        "This commit hasn't been fully parsed yet (or doesn't affect any ".
        "paths).");
      $content[] = $no_changes;
    } else {
      $change_panel = new AphrontPanelView();
      $change_panel->setHeader("Changes (".number_format($count).")");

      if ($count !== $original_changes_count) {
        $show_all_button = phutil_render_tag(
          'a',
          array(
            'class'   => 'button green',
            'href'    => '?show_all=true',
          ),
          phutil_escape_html('Show All Changes'));
        $warning_view = id(new AphrontErrorView())
          ->setSeverity(AphrontErrorView::SEVERITY_WARNING)
          ->setTitle(sprintf(
                       "Showing only the first %d changes out of %s!",
                       self::CHANGES_LIMIT,
                       number_format($original_changes_count)));

        $change_panel->appendChild($warning_view);
        $change_panel->addButton($show_all_button);
      }

      $change_panel->appendChild($change_table);

      $content[] = $change_panel;

      $changesets = DiffusionPathChange::convertToDifferentialChangesets(
        $changes);

      $vcs = $repository->getVersionControlSystem();
      switch ($vcs) {
        case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
          $vcs_supports_directory_changes = true;
          break;
        case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
          $vcs_supports_directory_changes = false;
          break;
        default:
          throw new Exception("Unknown VCS.");
      }

      $references = array();
      foreach ($changesets as $key => $changeset) {
        $file_type = $changeset->getFileType();
        if ($file_type == DifferentialChangeType::FILE_DIRECTORY) {
          if (!$vcs_supports_directory_changes) {
            unset($changesets[$key]);
            continue;
          }
        }

        $branch = $drequest->getBranchURIComponent(
          $drequest->getBranch());
        $filename = $changeset->getFilename();
        $reference = "{$branch}{$filename};".$drequest->getCommit();
        $references[$key] = $reference;
      }

      $change_list = new DifferentialChangesetListView();
      $change_list->setChangesets($changesets);
      $change_list->setRenderingReferences($references);
      $change_list->setRenderURI('/diffusion/'.$callsign.'/diff/');
      $change_list->setUser($user);

      // TODO: This is pretty awkward, unify the CSS between Diffusion and
      // Differential better.
      require_celerity_resource('differential-core-view-css');
      $change_list =
        '<div class="differential-primary-pane">'.
          $change_list->render().
        '</div>';

      $content[] = $change_list;
    }

    $content[] = $this->buildAddCommentView($commit);

    return $this->buildStandardPageResponse(
      $content,
      array(
        'title' => 'r'.$callsign.$commit->getCommitIdentifier(),
      ));
  }

  private function renderPropertyTable(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {

    $phids = array();
    if ($data->getCommitDetail('authorPHID')) {
      $phids[] = $data->getCommitDetail('authorPHID');
    }
    if ($data->getCommitDetail('reviewerPHID')) {
      $phids[] = $data->getCommitDetail('reviewerPHID');
    }
    if ($data->getCommitDetail('differential.revisionPHID')) {
      $phids[] = $data->getCommitDetail('differential.revisionPHID');
    }

    $handles = array();
    if ($phids) {
      $handles = id(new PhabricatorObjectHandleData($phids))
        ->loadHandles();
    }

    $props = array();

    $author_phid = $data->getCommitDetail('authorPHID');
    if ($data->getCommitDetail('authorPHID')) {
      $props['Author'] = $handles[$author_phid]->renderLink();
    } else {
      $props['Author'] = phutil_escape_html($data->getAuthorName());
    }

    $reviewer_phid = $data->getCommitDetail('reviewerPHID');
    $reviewer_name = $data->getCommitDetail('reviewerName');
    if ($reviewer_phid) {
      $props['Reviewer'] = $handles[$reviewer_phid]->renderLink();
    } else if ($reviewer_name) {
      $props['Reviewer'] = phutil_escape_html($reviewer_name);
    }

    $revision_phid = $data->getCommitDetail('differential.revisionPHID');
    if ($revision_phid) {
      $props['Differential Revision'] = $handles[$revision_phid]->renderLink();
    }

    if ($commit->getAuditStatus()) {
      $props['Audit'] = PhabricatorAuditCommitStatusConstants::getStatusName(
        $commit->getAuditStatus());
    }

    $request = $this->getDiffusionRequest();

    $contains = DiffusionContainsQuery::newFromDiffusionRequest($request);
    $branches = $contains->loadContainingBranches();

    if ($branches) {
      // TODO: Separate these into 'tracked' and other; link tracked branches.
      $branches = implode(', ', array_keys($branches));
      $branches = phutil_escape_html($branches);
      $props['Branches'] = $branches;
    }

    $rows = array();
    foreach ($props as $key => $value) {
      $rows[] =
        '<tr>'.
          '<th>'.$key.':</th>'.
          '<td>'.$value.'</td>'.
        '</tr>';
    }

    return
      '<table class="diffusion-commit-properties">'.
        implode("\n", $rows).
      '</table>';
  }

  private function buildAuditTable($commit) {
    $user = $this->getRequest()->getUser();

    $query = new PhabricatorAuditQuery();
    $query->withCommitPHIDs(array($commit->getPHID()));
    $audits = $query->execute();

    $view = new PhabricatorAuditListView();
    $view->setAudits($audits);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    $view->setHandles($handles);
    $view->setAuthorityPHIDs(
      PhabricatorAuditCommentEditor::loadAuditPHIDsForUser($user));

    $panel = new AphrontPanelView();
    $panel->setHeader('Audits');
    $panel->appendChild($view);

    return $panel;
  }

  private function buildComments($commit) {
    $user = $this->getRequest()->getUser();
    $comments = id(new PhabricatorAuditComment())->loadAllWhere(
      'targetPHID = %s ORDER BY dateCreated ASC',
      $commit->getPHID());

    $view = new DiffusionCommentListView();
    $view->setUser($user);
    $view->setComments($comments);

    $phids = $view->getRequiredHandlePHIDs();
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    $view->setHandles($handles);

    return $view;
  }

  private function buildAddCommentView($commit) {
    $user = $this->getRequest()->getUser();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $draft = id(new PhabricatorDraft())->loadOneWhere(
      'authorPHID = %s AND draftKey = %s',
      $user->getPHID(),
      'diffusion-audit-'.$commit->getID());
    if ($draft) {
      $draft = $draft->getDraft();
    } else {
      $draft = null;
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction('/audit/addcomment/')
      ->addHiddenInput('commit', $commit->getPHID())
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Action')
          ->setName('action')
          ->setID('audit-action')
          ->setOptions(PhabricatorAuditActionConstants::getActionNameMap()))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Comments')
          ->setName('content')
          ->setValue($draft)
          ->setID('audit-content')
          ->setCaption(phutil_render_tag(
            'a',
            array(
              'href' => PhabricatorEnv::getDoclink(
                'article/Remarkup_Reference.html'),
              'tabindex' => '-1',
              'target' => '_blank',
            ),
            'Formatting Reference')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($is_serious ? 'Submit' : 'Cook the Books'));

    $panel = new AphrontPanelView();
    $panel->setHeader($is_serious ? 'Audit Commit' : 'Creative Accounting');
    $panel->appendChild($form);

    require_celerity_resource('phabricator-transaction-view-css');

    Javelin::initBehavior('audit-preview', array(
      'uri'       => '/audit/preview/'.$commit->getID().'/',
      'preview'   => 'audit-preview',
      'content'   => 'audit-content',
      'action'    => 'audit-action',
    ));

    $preview_panel =
      '<div class="aphront-panel-preview">
        <div id="audit-preview">
          <div class="aphront-panel-preview-loading-text">
            Loading preview...
          </div>
        </div>
      </div>';

    $view = new AphrontNullView();
    $view->appendChild($panel);
    $view->appendChild($preview_panel);
    return $view;
  }

}
