<?php

final class PhabricatorFileUploadView extends AphrontView {

  private $user;

  private function getUser() {
    return $this->user;
  }
  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function render() {
    $user = $this->getUser();
    if (!$user) {
      throw new Exception("Call setUser() before render()!");
    }

    $form = new AphrontFormView();
    $form->setAction('/file/upload/');
    $form->setUser($user);

    $form
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel('File')
          ->setName('file')
          ->setError(true)
          ->setCaption(self::renderUploadLimit()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setCaption('Optional file display name.'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Upload')
          ->addCancelButton('/file/'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Upload File');

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);

    return $panel->render();
  }

  public static function renderUploadLimit() {
    $limit = PhabricatorEnv::getEnvConfig('storage.upload-size-limit');
    $limit = phabricator_parse_bytes($limit);
    if ($limit) {
      $formatted = phabricator_format_bytes($limit);
      return 'Maximum file size: '.phutil_escape_html($formatted);
    }

    $doc_href = PhabricatorEnv::getDocLink(
      'article/Configuring_File_Upload_Limits.html');
    $doc_link = phutil_render_tag(
      'a',
      array(
        'href'    => $doc_href,
        'target'  => '_blank',
      ),
      'Configuring File Upload Limits');

    return 'Upload limit is not configured, see '.$doc_link.'.';
  }
}

