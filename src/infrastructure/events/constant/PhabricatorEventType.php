<?php

/**
 * For detailed explanations of these events, see
 * @{article:Events User Guide: Installing Event Listeners}.
 *
 * @group events
 */
final class PhabricatorEventType extends PhutilEventType {

  const TYPE_CONTROLLER_CHECKREQUEST        = 'controller.checkRequest';

  const TYPE_MANIPHEST_WILLEDITTASK         = 'maniphest.willEditTask';
  const TYPE_MANIPHEST_DIDEDITTASK          = 'maniphest.didEditTask';

  const TYPE_DIFFERENTIAL_WILLSENDMAIL      = 'differential.willSendMail';
  const TYPE_DIFFERENTIAL_WILLMARKGENERATED = 'differential.willMarkGenerated';

  const TYPE_DIFFUSION_DIDDISCOVERCOMMIT    = 'diffusion.didDiscoverCommit';
  const TYPE_DIFFUSION_LOOKUPUSER           = 'diffusion.lookupUser';

  const TYPE_EDGE_WILLEDITEDGES             = 'edge.willEditEdges';
  const TYPE_EDGE_DIDEDITEDGES              = 'edge.didEditEdges';

  const TYPE_TEST_DIDRUNTEST                = 'test.didRunTest';

  const TYPE_UI_DIDRENDERACTIONS            = 'ui.didRenderActions';

  const TYPE_UI_WILLRENDEROBJECTS           = 'ui.willRenderObjects';
  const TYPE_UI_DDIDRENDEROBJECT            = 'ui.didRenderObject';
  const TYPE_UI_DIDRENDEROBJECTS            = 'ui.didRenderObjects';

}
