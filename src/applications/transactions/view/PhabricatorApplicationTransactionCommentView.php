<?php

final class PhabricatorApplicationTransactionCommentView
  extends AphrontView {

  private $submitButtonName;
  private $action;

  private $previewPanelID;
  private $previewTimelineID;
  private $previewToggleID;
  private $formID;
  private $statusID;
  private $commentID;
  private $draft;
  private $requestURI;
  private $showPreview = true;
  private $objectPHID;
  private $headerText;
  private $noPermission;
  private $fullWidth;
  private $infoView;
  private $editEngineLock;
  private $noBorder;
  private $requiresMFA;

  private $currentVersion;
  private $versionedDraft;
  private $commentActions;
  private $commentActionGroups = array();
  private $transactionTimeline;

  public function setObjectPHID($object_phid) {
    $this->objectPHID = $object_phid;
    return $this;
  }

  public function getObjectPHID() {
    return $this->objectPHID;
  }

  public function setShowPreview($show_preview) {
    $this->showPreview = $show_preview;
    return $this;
  }

  public function getShowPreview() {
    return $this->showPreview;
  }

  public function setRequestURI(PhutilURI $request_uri) {
    $this->requestURI = $request_uri;
    return $this;
  }
  public function getRequestURI() {
    return $this->requestURI;
  }

  public function setCurrentVersion($current_version) {
    $this->currentVersion = $current_version;
    return $this;
  }

  public function getCurrentVersion() {
    return $this->currentVersion;
  }

  public function setVersionedDraft(
    PhabricatorVersionedDraft $versioned_draft) {
    $this->versionedDraft = $versioned_draft;
    return $this;
  }

  public function getVersionedDraft() {
    return $this->versionedDraft;
  }

  public function setDraft(PhabricatorDraft $draft) {
    $this->draft = $draft;
    return $this;
  }

  public function getDraft() {
    return $this->draft;
  }

  public function setSubmitButtonName($submit_button_name) {
    $this->submitButtonName = $submit_button_name;
    return $this;
  }

  public function getSubmitButtonName() {
    return $this->submitButtonName;
  }

  public function setAction($action) {
    $this->action = $action;
    return $this;
  }

  public function getAction() {
    return $this->action;
  }

  public function setHeaderText($text) {
    $this->headerText = $text;
    return $this;
  }

  public function setFullWidth($fw) {
    $this->fullWidth = $fw;
    return $this;
  }

  public function setInfoView(PHUIInfoView $info_view) {
    $this->infoView = $info_view;
    return $this;
  }

  public function getInfoView() {
    return $this->infoView;
  }

  public function setCommentActions(array $comment_actions) {
    assert_instances_of($comment_actions, 'PhabricatorEditEngineCommentAction');
    $this->commentActions = $comment_actions;
    return $this;
  }

  public function getCommentActions() {
    return $this->commentActions;
  }

  public function setCommentActionGroups(array $groups) {
    assert_instances_of($groups, 'PhabricatorEditEngineCommentActionGroup');
    $this->commentActionGroups = $groups;
    return $this;
  }

  public function getCommentActionGroups() {
    return $this->commentActionGroups;
  }

  public function setNoPermission($no_permission) {
    $this->noPermission = $no_permission;
    return $this;
  }

  public function getNoPermission() {
    return $this->noPermission;
  }

  public function setEditEngineLock(PhabricatorEditEngineLock $lock) {
    $this->editEngineLock = $lock;
    return $this;
  }

  public function getEditEngineLock() {
    return $this->editEngineLock;
  }

  public function setRequiresMFA($requires_mfa) {
    $this->requiresMFA = $requires_mfa;
    return $this;
  }

  public function getRequiresMFA() {
    return $this->requiresMFA;
  }

  public function setTransactionTimeline(
    PhabricatorApplicationTransactionView $timeline) {

    $timeline->setQuoteTargetID($this->getCommentID());
    if ($this->getNoPermission() || $this->getEditEngineLock()) {
      $timeline->setShouldTerminate(true);
    }

    $this->transactionTimeline = $timeline;
    return $this;
  }

  public function render() {
    if ($this->getNoPermission()) {
      return null;
    }

    $lock = $this->getEditEngineLock();
    if ($lock) {
      return id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors(
          array(
            $lock->getLockedObjectDisplayText(),
          ));
    }

    $viewer = $this->getViewer();
    if (!$viewer->isLoggedIn()) {
      $uri = id(new PhutilURI('/login/'))
        ->replaceQueryParam('next', (string)$this->getRequestURI());
      return id(new PHUIObjectBoxView())
        ->setFlush(true)
        ->appendChild(
          javelin_tag(
            'a',
            array(
              'class' => 'login-to-comment button',
              'href' => $uri,
            ),
            pht('Log In to Comment')));
    }

    if ($this->getRequiresMFA()) {
      if (!$viewer->getIsEnrolledInMultiFactor()) {
        $viewer->updateMultiFactorEnrollment();
        if (!$viewer->getIsEnrolledInMultiFactor()) {
          $messages = array();
          $messages[] = pht(
            'You must provide multi-factor credentials to comment or make '.
            'changes, but you do not have multi-factor authentication '.
            'configured on your account.');
          $messages[] = pht(
            'To continue, configure multi-factor authentication in Settings.');

          return id(new PHUIInfoView())
            ->setSeverity(PHUIInfoView::SEVERITY_MFA)
            ->setErrors($messages);
        }
      }
    }

    $data = array();

    $comment = $this->renderCommentPanel();

    if ($this->getShowPreview()) {
      $preview = $this->renderPreviewPanel();
    } else {
      $preview = null;
    }

    if (!$this->getCommentActions()) {
      Javelin::initBehavior(
        'phabricator-transaction-comment-form',
        array(
          'formID'        => $this->getFormID(),
          'timelineID'    => $this->getPreviewTimelineID(),
          'panelID'       => $this->getPreviewPanelID(),
          'showPreview'   => $this->getShowPreview(),
          'actionURI'     => $this->getAction(),
        ));
    }

    require_celerity_resource('phui-comment-form-css');
    $image_uri = $viewer->getProfileImageURI();
    $image = javelin_tag(
      'div',
      array(
        'style' => 'background-image: url('.$image_uri.')',
        'class' => 'phui-comment-image',
        'aural' => false,
      ));
    $wedge = phutil_tag(
      'div',
      array(
        'class' => 'phui-timeline-wedge',
      ),
      '');

    $badge_view = $this->renderBadgeView();

    $anchor = id(new PhabricatorAnchorView())
      ->setAnchorName('reply');

    $comment_box = id(new PHUIObjectBoxView())
      ->setFlush(true)
      ->addClass('phui-comment-form-view')
      ->addSigil('phui-comment-form')
      ->appendChild($anchor)
      ->appendChild(
        phutil_tag(
          'h3',
          array(
            'class' => 'aural-only',
          ),
          pht('Add Comment')))
      ->appendChild($image)
      ->appendChild($badge_view)
      ->appendChild($wedge)
      ->appendChild($comment);

    return array($comment_box, $preview);
  }

  private function renderCommentPanel() {
    $viewer = $this->getViewer();

    $remarkup_control = id(new PhabricatorRemarkupControl())
      ->setViewer($viewer)
      ->setID($this->getCommentID())
      ->addClass('phui-comment-fullwidth-control')
      ->addClass('phui-comment-textarea-control')
      ->setCanPin(true)
      ->setName('comment');

    $draft_comment = '';
    $draft_metadata = array();
    $draft_key = null;

    $legacy_draft = $this->getDraft();
    if ($legacy_draft) {
      $draft_comment = $legacy_draft->getDraft();
      $draft_key = $legacy_draft->getDraftKey();
    }

    $versioned_draft = $this->getVersionedDraft();
    if ($versioned_draft) {
      $draft_comment = $versioned_draft->getProperty(
        'comment',
        $draft_comment);
      $draft_metadata = $versioned_draft->getProperty(
        'metadata',
        $draft_metadata);
    }

    $remarkup_control->setValue($draft_comment);

    if (!is_array($draft_metadata)) {
      $draft_metadata = array();
    }
    $remarkup_control->setRemarkupMetadata($draft_metadata);

    if (!$this->getObjectPHID()) {
      throw new PhutilInvalidStateException('setObjectPHID', 'render');
    }

    $version_key = PhabricatorVersionedDraft::KEY_VERSION;
    $version_value = $this->getCurrentVersion();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addSigil('transaction-append')
      ->setWorkflow(true)
      ->setFullWidth($this->fullWidth)
      ->setMetadata(
        array(
          'objectPHID' => $this->getObjectPHID(),
        ))
      ->setAction($this->getAction())
      ->setID($this->getFormID())
      ->addHiddenInput('__draft__', $draft_key)
      ->addHiddenInput($version_key, $version_value);

    $comment_actions = $this->getCommentActions();
    if ($comment_actions) {
      $action_map = array();
      $type_map = array();

      $comment_actions = mpull($comment_actions, null, 'getKey');

      $draft_actions = array();
      $draft_keys = array();
      if ($versioned_draft) {
        $draft_actions = $versioned_draft->getProperty('actions', array());

        if (!is_array($draft_actions)) {
          $draft_actions = array();
        }

        foreach ($draft_actions as $action) {
          $type = idx($action, 'type');
          $comment_action = idx($comment_actions, $type);
          if (!$comment_action) {
            continue;
          }

          $value = idx($action, 'value');
          $comment_action->setValue($value);

          $draft_keys[] = $type;
        }
      }

      foreach ($comment_actions as $key => $comment_action) {
        $key = $comment_action->getKey();
        $label = $comment_action->getLabel();

        $action_map[$key] = array(
          'key' => $key,
          'label' => $label,
          'type' => $comment_action->getPHUIXControlType(),
          'spec' => $comment_action->getPHUIXControlSpecification(),
          'initialValue' => $comment_action->getInitialValue(),
          'groupKey' => $comment_action->getGroupKey(),
          'conflictKey' => $comment_action->getConflictKey(),
          'auralLabel' => pht('Remove Action: %s', $label),
          'buttonText' => $comment_action->getSubmitButtonText(),
        );

        $type_map[$key] = $comment_action;
      }

      $options = $this->newCommentActionOptions($action_map);

      $action_id = celerity_generate_unique_node_id();
      $input_id = celerity_generate_unique_node_id();
      $place_id = celerity_generate_unique_node_id();

      $form->appendChild(
        phutil_tag(
          'input',
          array(
            'type' => 'hidden',
            'name' => 'editengine.actions',
            'id' => $input_id,
          )));

      $invisi_bar = phutil_tag(
        'div',
        array(
          'id' => $place_id,
          'class' => 'phui-comment-control-stack',
        ));

      $action_select = id(new AphrontFormSelectControl())
        ->addClass('phui-comment-fullwidth-control')
        ->addClass('phui-comment-action-control')
        ->setID($action_id)
        ->setOptions($options);

      $action_bar = phutil_tag(
        'div',
        array(
          'class' => 'phui-comment-action-bar grouped',
        ),
        array(
          $action_select,
        ));

      $form->appendChild($action_bar);

      $info_view = $this->getInfoView();
      if ($info_view) {
        $form->appendChild($info_view);
      }

      if ($this->getRequiresMFA()) {
        $message = pht(
          'You will be required to provide multi-factor credentials to '.
          'comment or make changes.');

        $form->appendChild(
          id(new PHUIInfoView())
            ->setSeverity(PHUIInfoView::SEVERITY_MFA)
            ->setErrors(array($message)));
      }

      $form->appendChild($invisi_bar);
      $form->addClass('phui-comment-has-actions');

      $timeline = $this->transactionTimeline;

      $view_data = array();
      if ($timeline) {
        $view_data = $timeline->getViewData();
      }

      Javelin::initBehavior(
        'comment-actions',
        array(
          'actionID' => $action_id,
          'inputID' => $input_id,
          'formID' => $this->getFormID(),
          'placeID' => $place_id,
          'panelID' => $this->getPreviewPanelID(),
          'timelineID' => $this->getPreviewTimelineID(),
          'actions' => $action_map,
          'showPreview' => $this->getShowPreview(),
          'actionURI' => $this->getAction(),
          'drafts' => $draft_keys,
          'defaultButtonText' => $this->getSubmitButtonName(),
          'viewData' => $view_data,
        ));
    }

    $submit_button = id(new AphrontFormSubmitControl())
      ->addClass('phui-comment-fullwidth-control')
      ->addClass('phui-comment-submit-control')
      ->setValue($this->getSubmitButtonName());

    $form
      ->appendChild($remarkup_control)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addClass('phui-comment-fullwidth-control')
          ->addClass('phui-comment-submit-control')
          ->addSigil('submit-transactions')
          ->setValue($this->getSubmitButtonName()));

    return $form;
  }

  private function renderPreviewPanel() {

    $preview = id(new PHUITimelineView())
      ->setID($this->getPreviewTimelineID());

    return phutil_tag(
      'div',
      array(
        'id'    => $this->getPreviewPanelID(),
        'style' => 'display: none',
        'class' => 'phui-comment-preview-view',
      ),
      $preview);
  }

  private function getPreviewPanelID() {
    if (!$this->previewPanelID) {
      $this->previewPanelID = celerity_generate_unique_node_id();
    }
    return $this->previewPanelID;
  }

  private function getPreviewTimelineID() {
    if (!$this->previewTimelineID) {
      $this->previewTimelineID = celerity_generate_unique_node_id();
    }
    return $this->previewTimelineID;
  }

  public function setFormID($id) {
    $this->formID = $id;
    return $this;
  }

  private function getFormID() {
    if (!$this->formID) {
      $this->formID = celerity_generate_unique_node_id();
    }
    return $this->formID;
  }

  private function getStatusID() {
    if (!$this->statusID) {
      $this->statusID = celerity_generate_unique_node_id();
    }
    return $this->statusID;
  }

  private function getCommentID() {
    if (!$this->commentID) {
      $this->commentID = celerity_generate_unique_node_id();
    }
    return $this->commentID;
  }

  private function newCommentActionOptions(array $action_map) {
    $options = array();
    $options['+'] = pht('Add Action...');

    // Merge options into groups.
    $groups = array();
    foreach ($action_map as $key => $item) {
      $group_key = $item['groupKey'];
      if (!isset($groups[$group_key])) {
        $groups[$group_key] = array();
      }
      $groups[$group_key][$key] = $item;
    }

    $group_specs = $this->getCommentActionGroups();
    $group_labels = mpull($group_specs, 'getLabel', 'getKey');

    // Reorder groups to put them in the same order as the recognized
    // group definitions.
    $groups = array_select_keys($groups, array_keys($group_labels)) + $groups;

    // Move options with no group to the end.
    $default_group = idx($groups, '');
    if ($default_group) {
      unset($groups['']);
      $groups[''] = $default_group;
    }

    foreach ($groups as $group_key => $group_items) {
      if (strlen($group_key)) {
        $group_label = idx($group_labels, $group_key, $group_key);
        $options[$group_label] = ipull($group_items, 'label');
      } else {
        foreach ($group_items as $key => $item) {
          $options[$key] = $item['label'];
        }
      }
    }

    return $options;
  }

  private function renderBadgeView() {
    $user = $this->getUser();
    $can_use_badges = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorBadgesApplication',
      $user);
    if (!$can_use_badges) {
      return null;
    }

    // Pull Badges from UserCache
    $badges = $user->getRecentBadgeAwards();
    $badge_view = null;
    if ($badges) {
      $badge_list = array();
      foreach ($badges as $badge) {
        $badge_view = id(new PHUIBadgeMiniView())
          ->setIcon($badge['icon'])
          ->setQuality($badge['quality'])
          ->setHeader($badge['name'])
          ->setTipDirection('E')
          ->setHref('/badges/view/'.$badge['id'].'/');

        $badge_list[] = $badge_view;
      }
      $flex = new PHUIBadgeBoxView();
      $flex->addItems($badge_list);
      $flex->setCollapsed(true);
      $badge_view = phutil_tag(
        'div',
        array(
          'class' => 'phui-timeline-badges',
        ),
        $flex);
    }

    return $badge_view;
  }

}
