<?php

final class PhabricatorBadgesEditController
  extends PhabricatorBadgesController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if ($id) {
      $badge = id(new PhabricatorBadgesQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$badge) {
        return new Aphront404Response();
      }
      $is_new = false;
    } else {
      $this->requireApplicationCapability(
        PhabricatorBadgesCreateCapability::CAPABILITY);

      $badge = PhabricatorBadgesBadge::initializeNewBadge($viewer);
      $is_new = true;
    }

    if ($is_new) {
      $title = pht('Create Badge');
      $button_text = pht('Create Badge');
      $cancel_uri = $this->getApplicationURI();
    } else {
      $title = pht(
        'Edit %s',
        $badge->getName());
      $button_text = pht('Save Changes');
      $cancel_uri = $this->getApplicationURI('view/'.$id.'/');
    }

    $e_name = true;
    $v_name = $badge->getName();

    $v_icon = $badge->getIcon();

    $v_flav = $badge->getFlavor();
    $v_desc = $badge->getDescription();
    $v_qual = $badge->getQuality();
    $v_stat = $badge->getStatus();

    $v_edit = $badge->getEditPolicy();

    $validation_exception = null;
    if ($request->isFormPost()) {
      $v_name = $request->getStr('name');
      $v_flav = $request->getStr('flavor');
      $v_desc = $request->getStr('description');
      $v_icon = $request->getStr('icon');
      $v_stat = $request->getStr('status');
      $v_qual = $request->getStr('quality');

      $v_view = $request->getStr('viewPolicy');
      $v_edit = $request->getStr('editPolicy');

      $type_name = PhabricatorBadgesTransaction::TYPE_NAME;
      $type_flav = PhabricatorBadgesTransaction::TYPE_FLAVOR;
      $type_desc = PhabricatorBadgesTransaction::TYPE_DESCRIPTION;
      $type_icon = PhabricatorBadgesTransaction::TYPE_ICON;
      $type_qual = PhabricatorBadgesTransaction::TYPE_QUALITY;
      $type_stat = PhabricatorBadgesTransaction::TYPE_STATUS;

      $type_edit = PhabricatorTransactions::TYPE_EDIT_POLICY;

      $xactions = array();

      $xactions[] = id(new PhabricatorBadgesTransaction())
        ->setTransactionType($type_name)
        ->setNewValue($v_name);

      $xactions[] = id(new PhabricatorBadgesTransaction())
        ->setTransactionType($type_flav)
        ->setNewValue($v_flav);

      $xactions[] = id(new PhabricatorBadgesTransaction())
        ->setTransactionType($type_desc)
        ->setNewValue($v_desc);

      $xactions[] = id(new PhabricatorBadgesTransaction())
        ->setTransactionType($type_icon)
        ->setNewValue($v_icon);

      $xactions[] = id(new PhabricatorBadgesTransaction())
        ->setTransactionType($type_qual)
        ->setNewValue($v_qual);

      $xactions[] = id(new PhabricatorBadgesTransaction())
        ->setTransactionType($type_stat)
        ->setNewValue($v_stat);

      $xactions[] = id(new PhabricatorBadgesTransaction())
        ->setTransactionType($type_edit)
        ->setNewValue($v_edit);

      $editor = id(new PhabricatorBadgesEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true);

      try {
        $editor->applyTransactions($badge, $xactions);
        $return_uri = $this->getApplicationURI('view/'.$badge->getID().'/');
        return id(new AphrontRedirectResponse())->setURI($return_uri);

      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_name = $ex->getShortMessage($type_name);
      }
    }

    if ($is_new) {
      $icon_uri = $this->getApplicationURI('icon/');
    } else {
      $icon_uri = $this->getApplicationURI('icon/'.$badge->getID().'/');
    }
    $icon_display = PhabricatorBadgesIcon::renderIconForChooser($v_icon);

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($badge)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name'))
          ->setValue($v_name)
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('flavor')
          ->setLabel(pht('Flavor Text'))
          ->setValue($v_flav))
      ->appendChild(
        id(new AphrontFormChooseButtonControl())
          ->setLabel(pht('Icon'))
          ->setName('icon')
          ->setDisplayValue($icon_display)
          ->setButtonText(pht('Choose Icon...'))
          ->setChooseURI($icon_uri)
          ->setValue($v_icon))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('quality')
          ->setLabel(pht('Quality'))
          ->setValue($v_qual)
          ->setOptions($badge->getQualityNameMap()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Status'))
          ->setName('status')
          ->setValue($v_stat)
          ->setOptions($badge->getStatusNameMap()))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setUser($viewer)
          ->setName('description')
          ->setLabel(pht('Description'))
          ->setValue($v_desc))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($badge)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setValue($v_edit)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($button_text)
          ->addCancelButton($cancel_uri));

    $crumbs = $this->buildApplicationCrumbs();
    if ($is_new) {
      $crumbs->addTextCrumb(pht('Create Badge'));
    } else {
      $crumbs->addTextCrumb(
        $badge->getName(),
        '/badges/view/'.$badge->getID().'/');
      $crumbs->addTextCrumb(pht('Edit'));
    }

    $box = id(new PHUIObjectBoxView())
      ->setValidationException($validation_exception)
      ->setHeaderText($title)
      ->appendChild($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
      ));
  }

}
