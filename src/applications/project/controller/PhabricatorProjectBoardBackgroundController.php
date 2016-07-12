<?php

final class PhabricatorProjectBoardBackgroundController
  extends PhabricatorProjectBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();
    $board_id = $request->getURIData('projectID');

    $board = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($board_id))
      ->needImages(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$board) {
      return new Aphront404Response();
    }

    if (!$board->getHasWorkboard()) {
      return new Aphront404Response();
    }

    $this->setProject($board);
    $id = $board->getID();

    $manage_uri = $this->getApplicationURI("board/{$id}/manage/");

    if ($request->isFormPost()) {
      $background_key = $request->getStr('backgroundKey');

      $xactions = array();

      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_BACKGROUND)
        ->setNewValue($background_key);

      id(new PhabricatorProjectTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($board, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($manage_uri);
    }

    $nav = $this->getProfileMenu();

    $crumbs = id($this->buildApplicationCrumbs())
      ->addTextCrumb(pht('Workboard'), "/project/board/{$board_id}/")
      ->addTextCrumb(pht('Manage'), $manage_uri)
      ->addTextCrumb(pht('Background Color'));

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    $group_info = array(
      'basic' => array(
        'label' => pht('Basics'),
      ),
      'solid' => array(
        'label' => pht('Solid Colors'),
      ),
      'gradient' => array(
        'label' => pht('Gradients'),
      ),
    );

    $groups = array();
    $options = PhabricatorProjectWorkboardBackgroundColor::getOptions();
    $option_groups = igroup($options, 'group');

    require_celerity_resource('people-profile-css');
    require_celerity_resource('phui-workboard-color-css');
    Javelin::initBehavior('phabricator-tooltips', array());

    foreach ($group_info as $group_key => $spec) {
      $buttons = array();

      $available_options = idx($option_groups, $group_key, array());
      foreach ($available_options as $option) {
        $buttons[] = $this->renderOptionButton($option);
      }

      $form->appendControl(
        id(new AphrontFormMarkupControl())
          ->setLabel($spec['label'])
          ->setValue($buttons));
    }

    // NOTE: Each button is its own form, so we can't wrap them in a normal
    // form.
    $layout_view = $form->buildLayoutView();

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Edit Background Color'))
      ->appendChild($layout_view);

    return $this->newPage()
      ->setTitle(
        array(
          pht('Edit Background Color'),
          $board->getDisplayName(),
        ))
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($form_box);
  }

  private function renderOptionButton(array $option) {
    $viewer = $this->getViewer();

    $icon = idx($option, 'icon');
    if ($icon) {
      $preview_class = null;
      $preview_content = id(new PHUIIconView())
        ->setIcon($icon, 'lightbluetext');
    } else {
      $preview_class = 'phui-workboard-'.$option['key'];
      $preview_content = null;
    }

    $preview = phutil_tag(
      'div',
      array(
        'class' => 'phui-workboard-color-preview '.$preview_class,
      ),
      $preview_content);

    $button = javelin_tag(
      'button',
      array(
        'class' => 'grey profile-image-button',
        'sigil' => 'has-tooltip',
        'meta' => array(
          'tip' => $option['name'],
          'size' => 300,
        ),
      ),
      $preview);

    $input = phutil_tag(
      'input',
      array(
        'type'  => 'hidden',
        'name'  => 'backgroundKey',
        'value' => $option['key'],
      ));

    return phabricator_form(
      $viewer,
      array(
        'class' => 'profile-image-form',
        'method' => 'POST',
      ),
      array(
        $button,
        $input,
      ));
  }

}
