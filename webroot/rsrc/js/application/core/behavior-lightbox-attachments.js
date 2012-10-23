/**
 * @provides javelin-behavior-lightbox-attachments
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-mask
 *           javelin-util
 */

JX.behavior('lightbox-attachments', function (config) {

  var lightbox     = null;
  var prev         = null;
  var next         = null;
  var x_margin     = 40;
  var y_margin     = 100;
  var downloadForm = JX.$H(config.downloadForm);

  function loadLightBox(e) {
    if (JX.Stratcom.pass()) {
      return;
    }
    e.prevent();

    var links = JX.DOM.scry(document, 'a', 'lightboxable');
    var phids = {};
    var data;
    for (var i = 0; i < links.length; i++) {
      data = JX.Stratcom.getData(links[i]);
      phids[data.phid] = links[i];
    }

    // Now that we have the big picture phid situation sorted out, figure
    // out how the actual node the user clicks fits into that big picture
    // and build some pretty UI to show the attachment.
    var target      = e.getNode('lightboxable');
    var target_data = JX.Stratcom.getData(target);
    var total       = JX.keys(phids).length;
    var current     = 1;
    var past_target = false;
    for (var phid in phids) {
      if (past_target) {
        next = phids[phid];
        break;
      } else if (phid == target_data.phid) {
        past_target = true;
      } else {
        prev = phids[phid];
        current++;
      }
    }

    var img_uri = '';
    var extra_status = '';
    var name_element = '';
    // for now, this conditional is always true
    // revisit if / when we decide to add non-images to lightbox view
    if (target_data.viewable) {
      img_uri = target_data.uri;
    } else {
      img_uri = config.defaultImageUri;
      extra_status = ' Image may not be representative of actual attachment.';
      name_element = JX.$N('div',
                           { className : 'attachment-name' },
                           target_data.name
                          );
    }

    var img = JX.$N('img',
                    {
                      className : 'loading',
                      alt       : target_data.name,
                      title     : target_data.name
                    }
                   );
    // Evil hack - onload events don't work through Stratcom to prevent
    // the inevitable systematic abuse if it was possible. This is a
    // weird case so just hack it...!
    img.onload = lightBoxOnload;

    lightbox = JX.$N('div',
                     {
                       className : 'lightbox-attachment',
                       sigil: 'lightbox-attachment'
                     },
                     img
                    );
    JX.DOM.appendContent(lightbox, name_element);

    var closeIcon = JX.$N('a',
                         {
                           className : 'lightbox-close',
                           href : '#'
                         }
                        );
    JX.DOM.listen(closeIcon, 'click', null, closeLightBox);
    JX.DOM.appendContent(lightbox, closeIcon);
    var leftIcon = '';
    if (next) {
      leftIcon = JX.$N('a',
                       {
                         className : 'lightbox-right',
                         href : '#'
                       }
                      );
      JX.DOM.listen(leftIcon,
                    'click',
                    null,
                    JX.bind(null, loadAnotherLightBox, next)
                   );
    }
    JX.DOM.appendContent(lightbox, leftIcon);
    var rightIcon = '';
    if (prev) {
      rightIcon = JX.$N('a',
                        {
                          className : 'lightbox-left',
                          href : '#'
                        }
                       );
      JX.DOM.listen(rightIcon,
                    'click',
                    null,
                    JX.bind(null, loadAnotherLightBox, prev)
                   );
    }
    JX.DOM.appendContent(lightbox, rightIcon);

    var statusSpan = JX.$N('span',
                           {
                             className: 'lightbox-status-txt'
                           },
                           'Image '+current+' of '+total+'.'+extra_status
                           );
    var form = JX.$N('form',
                     {
                       action     : target_data.dUri,
                       method     : 'POST',
                       className  : 'lightbox-download-form'
                     },
                     downloadForm
                    );
    JX.DOM.appendContent(form, JX.$N('button', {}, 'Download'));
    JX.DOM.listen(form,
                  'click',
                  null,
                  function (e) {
                    e.prevent();
                    form.submit();
                    // Firefox and probably IE need this trick to work.
                    // Removing a form from the DOM while its submitting is
                    // tricky business.
                    setTimeout(JX.bind(null, closeLightBox, e), 0);
                  }
                 );
    var downloadSpan = JX.$N('span',
                            {
                              className : 'lightbox-download'
                            },
                            form
                            );
    var statusHTML = JX.$N('div',
                           {
                             className : 'lightbox-status'
                           },
                           [statusSpan, downloadSpan]
                          );
    JX.DOM.appendContent(lightbox, statusHTML);
    JX.DOM.alterClass(document.body, 'lightbox-attached', true);
    JX.Mask.show('jx-dark-mask');
    document.body.appendChild(lightbox);
    img.src = img_uri;
  }

  // TODO - make this work with KeyboardShortcut, which means
  // making an uninstall / de-register for KeyboardShortcut
  function lightBoxHandleKeyDown(e) {
    if (!lightbox) {
      return;
    }
    var raw = e.getRawEvent();
    if (raw.altKey || raw.ctrlKey || raw.metaKey) {
      return;
    }
    if (JX.Stratcom.pass()) {
      return;
    }

    var handler = JX.bag;
    switch (e.getSpecialKey()) {
      case 'esc':
        handler = closeLightBox;
        break;
      case 'right':
      if (next) {
          handler = JX.bind(null, loadAnotherLightBox, next);
        }
        break;
      case 'left':
        if (prev) {
          handler = JX.bind(null, loadAnotherLightBox, prev);
        }
        break;
    }
    return handler(e);
  }

  function closeLightBox(e) {
    if (!lightbox) {
      return;
    }
    e.prevent();
    JX.DOM.remove(lightbox);
    JX.Mask.hide();
    JX.DOM.alterClass(document.body, 'lightbox-attached', false);
    lightbox = null;
    prev     = null;
    next     = null;
  }

  function loadAnotherLightBox(el, e) {
    if (!el) {
      return;
    }
    e.prevent();
    closeLightBox(e);
    el.click();
  }

  function lightBoxOnload(e) {
    if (!lightbox) {
      return;
    }
    var img = JX.DOM.find(lightbox, 'img');
    JX.DOM.alterClass(img, 'loading', false);
  }

  JX.Stratcom.listen(
    'click',
    ['lightboxable', 'tag:a'],
    loadLightBox
  );

  JX.Stratcom.listen(
    'keydown',
    null,
    lightBoxHandleKeyDown
  );


  // When the user clicks the background, close the lightbox.
  JX.Stratcom.listen(
    'click',
    'lightbox-attachment',
    function (e) {
      if (!lightbox) {
        return;
      }
      if (e.getTarget() != e.getNode('lightbox-attachment')) {
        // Don't close if they clicked some other element, like the image
        // itself or the next/previous arrows.
        return;
      }
      closeLightBox(e);
      e.kill();
    });

});
