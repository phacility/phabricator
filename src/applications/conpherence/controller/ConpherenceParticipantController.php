<?php

final class ConpherenceParticipantController extends ConpherenceController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $conpherence_id = $request->getURIData('id');
    if (!$conpherence_id) {
      return new Aphront404Response();
    }
    $conpherence = id(new ConpherenceThreadQuery())
      ->setViewer($viewer)
      ->withIDs(array($conpherence_id))
      ->needWidgetData(true)
      ->executeOne();
    if (!$conpherence) {
      return new Aphront404Response();
    }
    $this->setConpherence($conpherence);
    $content = $this->renderParticipantPaneContent();

    return id(new AphrontAjaxResponse())->setContent($content);
  }

  private function renderParticipantPaneContent() {
    $conpherence = $this->getConpherence();

    $widgets = array();
    $new_icon = id(new PHUIIconView())
      ->setIcon('fa-plus-square')
      ->setHref($this->getUpdateURI())
      ->setMetadata(array('widget' => null))
      ->addSigil('conpherence-widget-adder');

    $content = id(new ConpherenceParticipantView())
      ->setUser($this->getViewer())
      ->setConpherence($this->getConpherence())
      ->setUpdateURI($this->getUpdateURI());

    $widgets[] = phutil_tag(
      'div',
      array(
        'class' => 'widgets-header',
      ),
      id(new PHUIHeaderView())
      ->setHeader(pht('Participants'))
      ->addActionItem($new_icon));

    $widgets[] = javelin_tag(
      'div',
      array(
        'class' => 'widgets-body',
        'id' => 'widgets-people',
        'sigil' => 'widgets-people',
      ),
      $content);

    // without this implosion we get "," between each element in our widgets
    // array
    return array('widgets' => phutil_implode_html('', $widgets));
  }

  private function getUpdateURI() {
    $conpherence = $this->getConpherence();
    return $this->getApplicationURI('update/'.$conpherence->getID().'/');
  }

}
