<?php

final class PhragmentCreateController extends PhragmentController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $dblob = $request->getURIData('dblob');

    $parent = null;
    $parents = $this->loadParentFragments($dblob);
    if ($parents === null) {
      return new Aphront404Response();
    }
    if (count($parents) !== 0) {
      $parent = idx($parents, count($parents) - 1, null);
    }

    $parent_path = '';
    if ($parent !== null) {
      $parent_path = $parent->getPath();
    }
    $parent_path = trim($parent_path, '/');

    $fragment = id(new PhragmentFragment());

    $error_view = null;

    if ($request->isFormPost()) {
      $errors = array();

      $v_name = $request->getStr('name');
      $v_fileid = $request->getInt('fileID');
      $v_viewpolicy = $request->getStr('viewPolicy');
      $v_editpolicy = $request->getStr('editPolicy');

      if (strpos($v_name, '/') !== false) {
        $errors[] = pht("The fragment name can not contain '/'.");
      }

      $file = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withIDs(array($v_fileid))
        ->executeOne();
      if (!$file) {
        $errors[] = pht("The specified file doesn't exist.");
      }

      if (!count($errors)) {
        $depth = 1;
        if ($parent !== null) {
          $depth = $parent->getDepth() + 1;
        }

        PhragmentFragment::createFromFile(
          $viewer,
          $file,
          trim($parent_path.'/'.$v_name, '/'),
          $v_viewpolicy,
          $v_editpolicy);

        return id(new AphrontRedirectResponse())
          ->setURI('/phragment/browse/'.trim($parent_path.'/'.$v_name, '/'));
      } else {
        $error_view = id(new PHUIInfoView())
          ->setErrors($errors)
          ->setTitle(pht('Errors while creating fragment'));
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($fragment)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Parent Path'))
          ->setDisabled(true)
          ->setValue('/'.trim($parent_path.'/', '/')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('File ID'))
          ->setName('fileID'))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($viewer)
          ->setName('viewPolicy')
          ->setPolicyObject($fragment)
          ->setPolicies($policies)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($viewer)
          ->setName('editPolicy')
          ->setPolicyObject($fragment)
          ->setPolicies($policies)
          ->setCapability(PhabricatorPolicyCapability::CAN_EDIT))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Create Fragment'))
          ->addCancelButton(
            $this->getApplicationURI('browse/'.$parent_path)));

    $crumbs = $this->buildApplicationCrumbsWithPath($parents);
    $crumbs->addTextCrumb(pht('Create Fragment'));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Create Fragment'))
      ->setForm($form);

    if ($error_view) {
      $box->setInfoView($error_view);
    }

    $title = pht('Create Fragments');

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
