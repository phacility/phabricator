<?php

final class PhragmentPolicyController extends PhragmentController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $dblob = $request->getURIData('dblob');

    $parents = $this->loadParentFragments($dblob);
    if ($parents === null) {
      return new Aphront404Response();
    }
    $fragment = idx($parents, count($parents) - 1, null);

    $error_view = null;

    if ($request->isFormPost()) {
      $errors = array();

      $v_view_policy = $request->getStr('viewPolicy');
      $v_edit_policy = $request->getStr('editPolicy');
      $v_replace_children = $request->getBool('replacePoliciesOnChildren');

      $fragment->setViewPolicy($v_view_policy);
      $fragment->setEditPolicy($v_edit_policy);

      $fragment->save();

      if ($v_replace_children) {
        // If you can edit a fragment, you can forcibly set the policies
        // on child fragments, regardless of whether you can see them or not.
        $children = id(new PhragmentFragmentQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withLeadingPath($fragment->getPath().'/')
          ->execute();
        $children_phids = mpull($children, 'getPHID');

        $fragment->openTransaction();
          foreach ($children as $child) {
            $child->setViewPolicy($v_view_policy);
            $child->setEditPolicy($v_edit_policy);
            $child->save();
          }
        $fragment->saveTransaction();
      }

      return id(new AphrontRedirectResponse())
        ->setURI('/phragment/browse/'.$fragment->getPath());
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($fragment)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('viewPolicy')
          ->setPolicyObject($fragment)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setName('editPolicy')
          ->setPolicyObject($fragment)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
          ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'replacePoliciesOnChildren',
            'true',
            pht(
              'Replace policies on child fragments with '.
              'the policies above.')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Fragment Policies'))
          ->addCancelButton(
            $this->getApplicationURI('browse/'.$fragment->getPath())));

    $crumbs = $this->buildApplicationCrumbsWithPath($parents);
    $crumbs->addTextCrumb(pht('Edit Fragment Policies'));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Edit Fragment Policies: %s', $fragment->getPath()))
      ->setValidationException(null)
      ->setForm($form);

    $title = pht('Edit Fragment Policies');

    $view = array(
      $this->renderConfigurationWarningIfRequired(),
      $box,
    );

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
