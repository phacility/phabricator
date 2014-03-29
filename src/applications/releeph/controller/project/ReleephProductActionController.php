<?php

final class ReleephProductActionController extends ReleephProductController {

  private $id;
  private $action;

  public function willProcessRequest(array $data) {
    $this->id = $data['projectID'];
    $this->action = $data['action'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $product = id(new ReleephProjectQuery())
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->setViewer($viewer)
      ->executeOne();
    if (!$product) {
      return new Aphront404Response();
    }

    $this->setProduct($product);

    $product_id = $product->getID();
    $product_uri = $this->getProductViewURI($product);

    $action = $this->action;
    switch ($action) {
      case 'deactivate':
      case 'activate':
        break;
      default:
        throw new Aphront404Response();
    }

    if ($request->isFormPost()) {
      if ($action == 'activate') {
        $product->setIsActive(1)->save();
      } else {
        $product->deactivate($viewer)->save();
      }

      return id(new AphrontRedirectResponse())->setURI($product_uri);
    }

    if ($action == 'activate') {
      $title = pht('Activate Product?');
      $body = pht(
        'Reactivate the product %s?',
        phutil_tag('strong', array(), $product->getName()));
      $submit = pht('Reactivate Product');
      $short = pht('Deactivate');
    } else {
      $title = pht('Really Deactivate Product?');
      $body = pht(
        'Really deactivate the product %s?',
        phutil_tag('strong', array(), $product->getName()));
      $submit = pht('Deactivate Product');
      $short = pht('Activate');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->setShortTitle($short)
      ->appendParagraph($body)
      ->addSubmitButton($submit)
      ->addCancelButton($product_uri);
  }

}
