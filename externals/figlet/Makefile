# Makefile for figlet version 2.2.4 (26 Jan 2011)
# adapted from Makefile for figlet version 2.2.2 (05 July 2005)
# adapted from Makefile for figlet version 2.2 (15 Oct 1996)
# Copyright 1993, 1994,1995 Glenn Chappell and Ian Chai
# Copyright 1996, 1997, 1998, 1999, 2000, 2001 John Cowan
# Copyright 2002 Christiaan Keet
# Copyright 2011 Claudio Matsuoka

# Please notice that to follow modern standards and ease third-party
# package creation, binaries are now installed under BINDIR, and DESTDIR
# is reserved for the installation pathname prefix.
#
# Please make sure BINDIR, MANDIR, DEFAULTFONTDIR and
#   DEFAULTFONTFILE are defined to reflect the situation
#   on your computer.  See README for details.

# Don't change this even if your shell is different. The only reason
# for changing this is if sh is not in the same place.
SHELL = /bin/sh

# The C compiler and linker to use
CC	= gcc
CFLAGS	= -g -O2 -Wall -Wno-unused-value
LD	= gcc
LDFLAGS =

# Feature flags:
#   define TLF_FONTS to use TOIlet TLF fonts
XCFLAGS	= -DTLF_FONTS

# Where to install files
prefix	= /usr/local

# Where the executables should be put
BINDIR	= $(prefix)/bin

# Where the man page should be put
MANDIR	= $(prefix)/man

# Where figlet will search first for fonts (the ".flf" files).
DEFAULTFONTDIR = $(prefix)/share/figlet
# Use this definition if you can't put things in $(prefix)/share/figlet
#DEFAULTFONTDIR = fonts

# The filename of the font to be used if no other is specified,
#   without suffix.(standard is recommended, but any other can be
#   used). This font file should reside in the directory specified
#   by DEFAULTFONTDIR.
DEFAULTFONTFILE = standard

##
##  END OF CONFIGURATION SECTION
##

VERSION	= 2.2.5
DIST	= figlet-$(VERSION)
OBJS	= figlet.o zipio.o crc.o inflate.o utf8.o
BINS	= figlet chkfont figlist showfigfonts
MANUAL	= figlet.6 chkfont.6 figlist.6 showfigfonts.6
DFILES	= Makefile Makefile.tc $(MANUAL) $(OBJS:.o=.c) chkfont.c getopt.c \
	  figlist showfigfonts CHANGES FAQ README LICENSE figfont.txt \
	  crc.h inflate.h zipio.h utf8.h run-tests.sh figmagic

.c.o:
	$(CC) -c $(CFLAGS) $(XCFLAGS) -DDEFAULTFONTDIR=\"$(DEFAULTFONTDIR)\" \
		-DDEFAULTFONTFILE=\"$(DEFAULTFONTFILE)\" -o $*.o $<

all: $(BINS)

figlet: $(OBJS)
	$(LD) $(LDFLAGS) -o $@ $(OBJS)

chkfont: chkfont.o
	$(LD) $(LDFLAGS) -o $@ chkfont.o

clean:
	rm -f *.o *~ core figlet chkfont

install: all
	mkdir -p $(DESTDIR)$(BINDIR)
	mkdir -p $(DESTDIR)$(MANDIR)/man6
	mkdir -p $(DESTDIR)$(DEFAULTFONTDIR)
	cp $(BINS) $(DESTDIR)$(BINDIR)
	cp $(MANUAL) $(DESTDIR)$(MANDIR)/man6
	cp fonts/*.flf $(DESTDIR)$(DEFAULTFONTDIR)
	cp fonts/*.flc $(DESTDIR)$(DEFAULTFONTDIR)

dist:
	rm -Rf $(DIST) $(DIST).tar.gz
	mkdir $(DIST)/
	cp $(DFILES) $(DIST)/
	mkdir $(DIST)/fonts
	cp fonts/*.fl[fc] $(DIST)/fonts
	mkdir $(DIST)/tests
	cp tests/*txt tests/emboss.tlf $(DIST)/tests
	tar cvf - $(DIST) | gzip -9c > $(DIST).tar.gz
	rm -Rf $(DIST)
	tar xf $(DIST).tar.gz
	(cd $(DIST); make all check vercheck)
	@rm -Rf $(DIST)
	@echo
	@ls -l $(DIST).tar.gz

check:
	@echo "Run tests in `pwd`"
	@./run-tests.sh fonts
	@echo

vercheck:
	@printf "Infocode: "; ./figlet -I1
	@./figlet -v|sed -n '/Version/s/.*\(Version\)/\1/p'
	@printf "README: "; head -1 < README|sed 's/.*) //'
	@printf "FAQ: "; grep latest FAQ|sed 's/ and can.*//'
	@grep -h "^\.TH" *.6

$(OBJS) chkfont.o getopt.o: Makefile
chkfont.o: chkfont.c
crc.o: crc.c crc.h
figlet.o: figlet.c zipio.h
getopt.o: getopt.c
inflate.o: inflate.c inflate.h
zipio.o: zipio.c zipio.h inflate.h crc.h
