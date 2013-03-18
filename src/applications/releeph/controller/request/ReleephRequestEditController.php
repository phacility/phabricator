<?php

final class ReleephRequestEditController extends ReleephController {

  public function processRequest() {
    $request = $this->getRequest();

    $releeph_branch  = $this->getReleephBranch();
    $releeph_request = $this->getReleephRequest();

    $releeph_branch->populateReleephRequestHandles(
      $request->getUser(), array($releeph_request));

    $phids = array();
    $phids[] = $releeph_request->getRequestCommitPHID();
    $phids[] = $releeph_request->getRequestUserPHID();
    $phids[] = $releeph_request->getCommittedByUserPHID();

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($request->getUser())
      ->loadHandles();

    $age_string = phabricator_format_relative_time(
      time() - $releeph_request->getDateCreated());

    // Warn the user if we see this
    $notice_view = null;
    if ($request->getInt('existing')) {
      $notice_messages = array(
        'You are editing an existing pick request!',
        hsprintf(
          "Requested %s ago by %s",
          $age_string,
          $handles[$releeph_request->getRequestUserPHID()]->renderLink())
      );
      $notice_view = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setErrors($notice_messages);
    }

    // <aidehua> epriestley: Is it common to pass around a referer URL to
    // return from whence one came? [...]
    // <epriestley> If you only have two places, maybe consider some parameter
    // rather than the full URL.
    switch ($request->getStr('origin')) {
      case 'request':
        $origin_uri = '/RQ'.$releeph_request->getID();
        break;

      case 'branch':
      default:
        $origin_uri = $releeph_request->loadReleephBranch()->getURI();
        break;
    }

    $errors = array();

    $selector = $this->getReleephProject()->getReleephFieldSelector();
    $fields = $selector->getFieldSpecifications();
    foreach ($fields as $field) {
      $field
        ->setReleephProject($this->getReleephProject())
        ->setReleephBranch($this->getReleephBranch())
        ->setReleephRequest($this->getReleephRequest());
    }

    if ($request->isFormPost()) {
      foreach ($fields as $field) {
        if ($field->isEditable()) {
          try {
            $field->setValueFromAphrontRequest($request);
          } catch (ReleephFieldParseException $ex) {
            $errors[] = $ex->getMessage();
          }
        }
      }

      if (!$errors) {
        $releeph_request->save();
        return id(new AphrontRedirectResponse())->setURI($origin_uri);
      }
    }

    /**
     * Build the rest of the page
     */
    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
      $error_view->setTitle('Form Errors');
    }

    $form = id(new AphrontFormView())
      ->setUser($request->getUser())
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Original Commit')
          ->setValue(
            $handles[$releeph_request->getRequestCommitPHID()]->renderLink()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Requestor')
          ->setValue(hsprintf(
            '%s %s ago',
            $handles[$releeph_request->getRequestUserPHID()]->renderLink(),
            $age_string)));

    // Fields
    foreach ($fields as $field) {
      if ($field->isEditable()) {
        $control = $field->renderEditControl($request);
        $form->appendChild($control);
      }
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($origin_uri, 'Cancel')
          ->setValue('Save'));

    $panel = id(new AphrontPanelView())
      ->setHeader('Edit Pick Request')
      ->setWidth(AphrontPanelView::WIDTH_FORM)
      ->appendChild($form);

    return $this->buildStandardPageResponse(
      array($notice_view, $error_view, $panel),
      array('title', 'Edit Pick Request'));
  }
}
