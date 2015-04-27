<?php

final class PhabricatorFileUploadController extends PhabricatorFileController {

  public function isGlobalDragAndDropUploadEnabled() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $file = PhabricatorFile::initializeNewFile();

    $e_file = true;
    $errors = array();
    if ($request->isFormPost()) {
      $view_policy = $request->getStr('viewPolicy');

      if (!$request->getFileExists('file')) {
        $e_file = pht('Required');
        $errors[] = pht('You must select a file to upload.');
      } else {
        $file = PhabricatorFile::newFromPHPUpload(
          idx($_FILES, 'file'),
          array(
            'name'        => $request->getStr('name'),
            'authorPHID'  => $viewer->getPHID(),
            'viewPolicy'  => $view_policy,
            'isExplicitUpload' => true,
          ));
      }

      if (!$errors) {
        return id(new AphrontRedirectResponse())->setURI($file->getInfoURI());
      }

      $file->setViewPolicy($view_policy);
    }

    $support_id = celerity_generate_unique_node_id();
    $instructions = id(new AphrontFormMarkupControl())
      ->setControlID($support_id)
      ->setControlStyle('display: none')
      ->setValue(hsprintf(
        '<br /><br /><strong>%s</strong> %s<br /><br />',
        pht('Drag and Drop:'),
        pht(
          'You can also upload files by dragging and dropping them from your '.
          'desktop onto this page or the Phabricator home page.')));

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($file)
      ->execute();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel(pht('File'))
          ->setName('file')
          ->setError($e_file))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($request->getStr('name')))
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setUser($viewer)
          ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
          ->setPolicyObject($file)
          ->setPolicies($policies)
          ->setName('viewPolicy'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Upload'))
          ->addCancelButton('/file/'))
      ->appendChild($instructions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Upload'), $request->getRequestURI());

    $title = pht('Upload File');

    $global_upload = id(new PhabricatorGlobalUploadTargetView())
      ->setUser($viewer)
      ->setShowIfSupportedID($support_id);

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
        $global_upload,
      ),
      array(
        'title' => $title,
      ));
  }

}
