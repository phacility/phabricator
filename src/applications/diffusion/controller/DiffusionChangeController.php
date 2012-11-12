<?php

final class DiffusionChangeController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->diffusionRequest;

    $content = array();

    $diff_query = DiffusionDiffQuery::newFromDiffusionRequest($drequest);
    $changeset = $diff_query->loadChangeset();

    if (!$changeset) {
      // TODO: Refine this.
      return new Aphront404Response();
    }

    $callsign = $drequest->getRepository()->getCallsign();
    $changesets = array(
      0 => $changeset,
    );

    $changeset_view = new DifferentialChangesetListView();
    $changeset_view->setChangesets($changesets);
    $changeset_view->setVisibleChangesets($changesets);
    $changeset_view->setRenderingReferences(
      array(
        0 => $diff_query->getRenderingReference(),
      ));

    $raw_params = array(
      'action' => 'browse',
      'params' => array(
        'view' => 'raw',
      ),
    );
    $right_uri = $drequest->generateURI($raw_params);
    $raw_params['params']['before'] = $drequest->getRawCommit();
    $left_uri = $drequest->generateURI($raw_params);
    $changeset_view->setRawFileURIs($left_uri, $right_uri);

    $changeset_view->setRenderURI(
      '/diffusion/'.$callsign.'/diff/');
    $changeset_view->setWhitespace(
      DifferentialChangesetParser::WHITESPACE_SHOW_ALL);
    $changeset_view->setUser($this->getRequest()->getUser());

    $content[] = $this->buildCrumbs(
      array(
        'branch' => true,
        'path'   => true,
        'view'   => 'change',
      ));

    // TODO: This is pretty awkward, unify the CSS between Diffusion and
    // Differential better.
    require_celerity_resource('differential-core-view-css');
    $content[] =
      '<div class="differential-primary-pane">'.
        $changeset_view->render().
      '</div>';

    $nav = $this->buildSideNav('change', true);
    $nav->appendChild($content);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Change',
      ));
  }

}
