<?php

final class PhragmentCreateController extends PhragmentController {

  private $dblob;

  public function willProcessRequest(array $data) {
    $this->dblob = idx($data, "dblob", "");
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $parent = null;
    $parents = $this->loadParentFragments($this->dblob);
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
        $errors[] = pht('The fragment name can not contain \'/\'.');
      }

      $file = id(new PhabricatorFile())->load($v_fileid);
      if ($file === null) {
        $errors[] = pht('The specified file doesn\'t exist.');
      }

      if (!count($errors)) {
        $depth = 1;
        if ($parent !== null) {
          $depth = $parent->getDepth() + 1;
        }

        $version = id(new PhragmentFragmentVersion());
        $version->setSequence(0);
        $version->setFragmentPHID(''); // Can't set this yet...
        $version->setFilePHID($file->getPHID());
        $version->save();

        $fragment->setPath(trim($parent_path.'/'.$v_name, '/'));
        $fragment->setDepth($depth);
        $fragment->setLatestVersionPHID($version->getPHID());
        $fragment->setViewPolicy($v_viewpolicy);
        $fragment->setEditPolicy($v_editpolicy);
        $fragment->save();

        $version->setFragmentPHID($fragment->getPHID());
        $version->save();

        return id(new AphrontRedirectResponse())
          ->setURI('/phragment/browse/'.trim($parent_path.'/'.$v_name, '/'));
      } else {
        $error_view = id(new AphrontErrorView())
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
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Create Fragment')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Create Fragment')
      ->setValidationException(null)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box),
      array(
        'title' => pht('Create Fragment'),
        'device' => true));
  }

}
