<?php

final class PhabricatorFactHomeController
  extends PhabricatorFactController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $chart = id(new PhabricatorDemoChartEngine())
      ->setViewer($viewer)
      ->newStoredChart();

    return id(new AphrontRedirectResponse())->setURI($chart->getURI());
  }

}
