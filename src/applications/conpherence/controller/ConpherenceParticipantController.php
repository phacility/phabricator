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
      ->needParticipants(true)
      ->executeOne();

    if (!$conpherence) {
      return new Aphront404Response();
    }

    $uri = $this->getApplicationURI('update/'.$conpherence->getID().'/');
    $content = id(new ConpherenceParticipantView())
      ->setUser($this->getViewer())
      ->setConpherence($conpherence)
      ->setUpdateURI($uri);

    $content = array('widgets' => $content);

    return id(new AphrontAjaxResponse())->setContent($content);
  }

}
