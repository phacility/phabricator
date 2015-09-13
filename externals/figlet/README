About FIGlet (Frank, Ian & Glenn's Letters) release 2.2.5, 31 May 2012
--------------------------------------------------------------------------
FIGlet is a program that creates large characters out of ordinary
screen characters
 _ _ _          _   _     _       
| (_) | _____  | |_| |__ (_)___   
| | | |/ / _ \ | __| '_ \| / __|  
| | |   <  __/ | |_| | | | \__ \_ 
|_|_|_|\_\___|  \__|_| |_|_|___(_)
                                  
(This is meant to be viewed in a monospaced font.)  FIGlet can create
characters in many different styles and can kern and "smush" these
characters together in various ways.  FIGlet output is generally
reminiscent of the sort of "signatures" many people like to put at the
end of e-mail and UseNet messages.

If you like FIGlet (hey, even if you *hate* FIGlet), please send an
e-mail message to <info@figlet.org>

The official FIGlet web page: http://www.figlet.org/

Files -- Unix version
---------------------

README       -- This file.
figlet.c     -- The FIGlet source code.
zipio.h,     -- A package for reading ZIP archives
zipio.c,
inflate.c,
crc.c
utf8.h,      -- A package to convert strings between UTF-8 and UCS-4.
utf8.c
getopt.c     -- Source for the standard "getopt" routine, in case you
                don't have it in your C library.  Not used by default.
Makefile     -- The FIGlet makefile.  Used by the make command. 
figlet.6     -- The FIGlet man(ual) page. 
figlist      -- Script that lists available fonts and control files.
showfigfonts -- Script that gives a sample of each available font.
chkfont.c    -- Source code for chkfont: a program that checks FIGlet
                fonts for formatting errors.  You can ignore this file
                unless you intend to design or edit fonts.
figfont.txt  -- Text file that explains the format of FIGlet fonts.
                You can ignore this file unless you intend to design
                or edit fonts.
fonts        -- Directory containing fonts and control files.
<xxx>.flf    -- All files ending in ".flf" are FIGlet font files.
<xxx>.flc    -- All files ending in ".flc" are FIGlet control files.

Files -- DOS version
--------------------

README       -- This file
figlet.exe   -- The FIGlet program.
figlet.man   -- The FIGlet man(ual) page. 
showall.bat  -- Batch file that lists available fonts and samples of each.
chkfont.exe  -- A program that checks FIGlet fonts for formatting errors.
                You can ignore this file unless you intend to design
                or edit fonts.
figfont.txt  -- Text file that explains the format of FIGlet fonts.
                You can ignore this file unless you intend to design
                or edit fonts.
fonts        -- Directory containing fonts and control files.
<xxx>.flf    -- All files ending in ".flf" are FIGlet font files.
<xxx>.flc    -- All files ending in ".flc" are FIGlet control files.


Installing FIGlet --- Unix version
----------------------------------

First decide in which directories FIGlet and the FIGlet font files
(the ".flf" files) will be stored (we recommend "/usr/games" and
"/usr/games/lib/figlet.dir", respectively) and which will be the
default font (we recommend "standard.flf").

Edit "Makefile", and set the variables DEFAULTFONTDIR and
DEFAULTFONTFILE to the appropriate values.  Set DEFAULTFONTDIR to be
the full pathname of the directory in which you will keep the FIGlet
font files.  Set DEFAULTFONTFILE to be the filename of the default
font.

At this point, you have two choices:

(1) Just compile FIGlet.  To go this, go into the directory containing
the FIGlet source, and type "make figlet".  Then copy the various files
to the proper locations.  The executable (figlet), along with figlist
and showfigfonts, goes wherever you keep your executables.  The fonts
(<xxx>.flf) and control files (<xxx>.flc) go in the default font
directory.  The man page (figlet.6) goes in section 6 (usually
/usr/man/man6).  If you cannot, or do not want to, install the man page,
you can probably still read it using

        nroff -man figlet.6 | more

(2) Do a complete installation.  To do the this, set the variables
BINDIR and MANDIR in Makefile to the appropriate values.  BINDIR
should be the full pathname of the directory in which the executable
files should be put (we recommend "/usr/games");  MANDIR should be the
full pathname of the directory in which the figlet man page should be
put, generally "/usr/man/man6".  Once DEFAULTFONTDIR, DEFAULTFONTFILE,
BINDIR and MANDIR have been set, in the directory containing the FIGlet
source, type "make install".

If space is a problem, the only files you absolutely must have to run
figlet are "figlet" (the executable) and at least one font (preferably
the one you chose to be the default font).

Installing FIGlet -- DOS version
--------------------------------

Unpack the ZIPfile using PKUNZIP, Info-Zip UNZIP, WinUnzip, or any other
ZIP-compatible program.  Be sure to use the -d option with PKUNZIP
in order to preserve the directory structure.  We recommend that you
unpack the archive into C:\FIGLET, but any directory will do.

If you unpack the archive on top of an older version of FIGlet, be
sure to delete the file FIGLET.COM.  The executable program in this
release is named FIGLET.EXE.  You can keep your old fonts by putting
them in a FONTS subdirectory before unpacking.  (There are upgraded
versions of the standard fonts in the archive.)

Using FIGlet
------------

(Note: FIGlet needs a good thorough tutorial.  Currently I don't have
the time to write one, but if anyone wants to do so, go right ahead.
I'd be glad to help out a little.  Write us at <ianchai@usa.net> if
you're interested.  -GGC-)

At the shell prompt, type "figlet".  Then type, say, "Hello, world!"
and press return.  "Hello, world!" in nice, big, designer characters
should appear on your screen.  If you chose standard.flf to be the
default font, you should see
 _   _      _ _                             _     _ _ 
| | | | ___| | | ___    __      _____  _ __| | __| | |
| |_| |/ _ \ | |/ _ \   \ \ /\ / / _ \| '__| |/ _` | |
|  _  |  __/ | | (_) |   \ V  V / (_) | |  | | (_| |_|
|_| |_|\___|_|_|\___( )   \_/\_/ \___/|_|  |_|\__,_(_)
                    |/                                
Then type something else, or type an EOF (typically control-D) to quit
FIGlet.

Now you can send the output of figlet to a file (e.g., "figlet > file")
and e-mail it to your friends (who will probably say, "Wow!  It must
have taken you hours to put that together!")

To use other fonts, use the "-f" command line option.  For example, if
you had said "figlet -f smslant" above, you would have seen
   __ __    ____                         __   ____
  / // /__ / / /__      _    _____  ____/ /__/ / /
 / _  / -_) / / _ \_   | |/|/ / _ \/ __/ / _  /_/ 
/_//_/\__/_/_/\___( )  |__,__/\___/_/ /_/\_,_(_)  
                  |/                              

Here are some other useful command line options:

-c   center -- centers the output of FIGlet.
-k   tells FIGlet to kern characters without smushing them together.
-t   terminal -- FIGlet asks your terminal how wide it is, and uses
     this to determine when to break lines.  Normally, FIGlet assumes
     80 columns so that people with wide terminals won't annoy the
     people they e-mail FIGlet output to.
-p   paragraph mode -- eliminates some spurious line breaks when piping
     a multi-line file through FIGlet.
-v   version -- prints information about your copy of FIGlet.

For in-depth explanations of these and other options, see the man page.
DOS users, see figlet.man.


Other Fonts & Mailing List
--------------------------

A good number of FIGlet fonts have been developed, most of which are
not included in the standard FIGlet package.  Many of these can be
obtained from http://www.figlet.org/   Some non-Roman fonts are 
available at this site.  As of this writing, we have Hebrew, Cyrillic
(Russian) and Greek.

There are 3 mailing lists available for FIGlet:
	 figlet@figlet.org           General discussion of FIGlet
	 figletfonts@figlet.org      Announcements about fonts 
	 figletsoftware@figlet.org   Announcements about software 
	 (The last two lists are moderated)

To subscribe or unsubscribe from the FIGlet mailing lists, please visit 
the corresponding URL:
	 http://www.figlet.org/mailman/listinfo/figlet 
	 http://www.figlet.org/mailman/listinfo/figletfonts 
	 http://www.figlet.org/mailman/listinfo/figletsoftware 

Also, for those who maintain archives of figlet fonts, please note that
all of the standard fonts have been changed, as of release 2.1, to
include non-ASCII characters.  These fonts are the following:

big.flf (also contains Greek)
banner.flf (also contains Cyrillic and Japanese katakana)
block.flf
bubble.flf
digital.flf
ivrit.flf (right-to-left, also contains Hebrew)
lean.flf
mini.flf
script.flf
shadow.flf
slant.flf
small.flf
smscript.flf
smshadow.flf
smslant.dld
standard.flf
term.flf

The new versions of these fonts can be identified by the words "figlet
release 2.1" somewhere in the first few lines.  


Other Stuff
-----------

FIGlet is available for operating systems other than Unix.  
Please see ftp://ftp.figlet.org/program/

Although you don't have to design your own fonts to use FIGlet, we'd
certainly like it if lots of people did make up new FIGlet fonts.  If
you feel like giving it a try, see the "FONT FILE FORMAT" section of
the man page.  If you do design a font, please let us know by mailing us
at <info@figlet.org>

See "Other Things to Try" in the EXAMPLES section of the man page
for... well... other things to try.


Authors
-------

FIGlet was written mostly by Glenn Chappell <c486scm@semovm.semo.edu>.  The
author not being an e-mail fanatic, most correspondence (bug reports, rave
reviews, etc.) used to be handled to his secretary (who is definitely
an e-mail fanatic), Ian Chai <ianchai@usa.net> and has since moved on to 
another FIGlet enthusiast, Christiaan Keet <info@figlet.org>. Current
maintenance is conducted by Claudio Matsuoka <cmatsuoka@gmail.com>.

