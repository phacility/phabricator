<?php

final class HarbormasterBuildableActionController
  extends HarbormasterController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');
    $action = $request->getURIData('action');

    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needBuilds(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$buildable) {
      return new Aphront404Response();
    }

    $message =
      HarbormasterBuildMessageTransaction::getTransactionObjectForMessageType(
        $action);
    if (!$message) {
      return new Aphront404Response();
    }

    $return_uri = '/'.$buildable->getMonogram();

    // See T13348. Actions may apply to only a subset of builds, so give the
    // user a preview of what will happen.

    $can_send = array();

    $rows = array();
    $builds = $buildable->getBuilds();
    foreach ($builds as $key => $build) {
      $exception = null;
      try {
        $message->assertCanSendMessage($viewer, $build);
        $can_send[$key] = $build;
      } catch (HarbormasterMessageException $ex) {
        $exception = $ex;
      }

      if (!$exception) {
        $icon_icon = $message->getIcon();
        $icon_color = 'green';

        $title = $message->getHarbormasterBuildMessageName();
        $body = $message->getHarbormasterBuildableMessageEffect();
      } else {
        $icon_icon = 'fa-times';
        $icon_color = 'red';

        $title = $ex->getTitle();
        $body = $ex->getBody();
      }

      $icon = id(new PHUIIconView())
        ->setIcon($icon_icon)
        ->setColor($icon_color);

      $build_name = phutil_tag(
        'a',
        array(
          'href' => $build->getURI(),
          'target' => '_blank',
        ),
        pht('%s %s', $build->getObjectName(), $build->getName()));

      $rows[] = array(
        $icon,
        $build_name,
        $title,
        $body,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          null,
          pht('Build'),
          pht('Action'),
          pht('Details'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'pri',
          'wide',
        ));

    $table = phutil_tag(
      'div',
      array(
        'class' => 'mlt mlb',
      ),
      $table);

    if ($request->isDialogFormPost() && $can_send) {
      $editor = id(new HarbormasterBuildableTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      $xaction_type = HarbormasterBuildableMessageTransaction::TRANSACTIONTYPE;

      $xaction = id(new HarbormasterBuildableTransaction())
        ->setTransactionType($xaction_type)
        ->setNewValue($action);

      $editor->applyTransactions($buildable, array($xaction));

      foreach ($can_send as $build) {
        $build->sendMessage(
          $viewer,
          $message->getHarbormasterBuildMessageType());
      }

      return id(new AphrontRedirectResponse())->setURI($return_uri);
    }

    if (!$builds) {
      $title = pht('No Builds');
      $body = pht(
        'This buildable has no builds, so you can not issue any commands.');
    } else {
      if ($can_send) {
        $title = $message->newBuildableConfirmPromptTitle(
          $builds,
          $can_send);

        $body = $message->newBuildableConfirmPromptBody(
          $builds,
          $can_send);
      } else {
        $title = pht('Unable to Send Command');
        $body = pht(
          'You can not send this command to any of the current builds '.
          'for this buildable.');
      }

      $body = array(
        pht('Builds for this buildable:'),
        $table,
        $body,
      );
    }

    $warnings = $message->newBuildableConfirmPromptWarnings(
      $builds,
      $can_send);

    if ($warnings) {
      $body[] = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors($warnings);
    }

    $submit = $message->getHarbormasterBuildableMessageName();

    $dialog = $this->newDialog()
      ->setWidth(AphrontDialogView::WIDTH_FULL)
      ->setTitle($title)
      ->appendChild($body)
      ->addCancelButton($return_uri);

    if ($can_send) {
      $dialog->addSubmitButton($submit);
    }

    return $dialog;
  }

}
