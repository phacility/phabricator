<?php

final class PhabricatorMacroDeleteController
  extends PhabricatorMacroController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $macro = id(new PhabricatorFileImageMacro())->load($this->id);
    if (!$macro) {
      return new Aphront404Response();
    }

    $request = $this->getRequest();

    if ($request->isDialogFormPost()) {
      $macro->delete();
      return id(new AphrontRedirectResponse())->setURI(
        $this->getApplicationURI());
    }

    $dialog = new AphrontDialogView();
    $dialog
      ->setUser($request->getUser())
      ->setTitle('Really delete macro?')
      ->appendChild(
        '<p>Really delete the much-beloved image macro "'.
        phutil_escape_html($macro->getName()).'"? It will be sorely missed.'.
        '</p>')
      ->setSubmitURI($this->getApplicationURI('/delete/'.$this->id.'/'))
      ->addSubmitButton('Delete')
      ->addCancelButton($this->getApplicationURI());


    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
