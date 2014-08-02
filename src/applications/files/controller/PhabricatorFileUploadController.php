<?php

final class PhabricatorFileUploadController extends PhabricatorFileController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $file = new PhabricatorFile();

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
          ->setError($e_file)
          ->setCaption($this->renderUploadLimit()))
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

  private function renderUploadLimit() {
    $limit = PhabricatorEnv::getEnvConfig('storage.upload-size-limit');
    $limit = phutil_parse_bytes($limit);
    if ($limit) {
      $formatted = phutil_format_bytes($limit);
      return 'Maximum file size: '.$formatted;
    }

    $doc_href = PhabricatorEnv::getDocLink(
      'Configuring File Upload Limits');
    $doc_link = phutil_tag(
      'a',
      array(
        'href'    => $doc_href,
        'target'  => '_blank',
      ),
      'Configuring File Upload Limits');

    return hsprintf('Upload limit is not configured, see %s.', $doc_link);
  }

}
