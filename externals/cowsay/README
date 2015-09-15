===========
cowsay 3.03
===========

cowsay is a configurable talking cow, written in Perl.  It operates
much as the figlet program does, and it written in the same spirit
of silliness.

cowsay is actually a pretty old program.  It has not really been
released before, and I am releasing it in the hope that someone
other than myself will be amused by it. 

The first major version of cowsay had one cow and one message
template: $foo is $verb $bar.  Not very flexible, but people managed
to do pretty interesting things with it.  The second major version
scrapped many of the limitations of the first, by allowing arbitrary
messages, multiple cowfiles, and even support for cows talking in
figlet.  The third version was a rewrite of the second into Perl
5, whereupon the code got a lot smaller and more manageable. :-)

If you are using Perl 5.004, you may have problems with Text::Wrap.
(Yeesh, this module changes more than it should...)  I've included
a diff for the Text::Wrap (version 97.011701) that is shipped with
5.004_04; the concept is simple enough that even older Perls can
take advantage of this silly little patch; if there is a "sub fill"
in the documentation for the module, copy it to a more useful
section of that file.  If not, just take "sub fill" wholesale from
the patch.  Oh, and consider upgrading to 5.005_03 or later.
Please.  You'll like it, I promise.

To install cowsay, consult the INSTALL file in this directory.

For the terms and conditions of use, consult the LICENSE file in
this directory.

-- Tony Monroe (tony@nog.net)

$Id: README,v 1.3 2000/05/28 06:24:46 tony Exp $
