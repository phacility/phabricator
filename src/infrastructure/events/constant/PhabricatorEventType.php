<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * For detailed explanations of these events, see
 * @{article:Events User Guide: Installing Event Listeners}.
 *
 * @group events
 */
final class PhabricatorEventType extends PhutilEventType {

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
