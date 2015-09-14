/****************************************************************************

  FIGlet Copyright 1991, 1993, 1994 Glenn Chappell and Ian Chai
  FIGlet Copyright 1996, 1997, 1998, 1999, 2000, 2001 John Cowan
  FIGlet Copyright 2002 Christiaan Keet
  FIGlet Copyright 2011, 2012 Claudio Matsuoka
  Portions written by Paul Burton and Christiaan Keet
  Internet: <info@figlet.org>
  FIGlet, along with the various FIGlet fonts and documentation, is
    copyrighted under the provisions of the New BSD License (3-clause)
    (as listed in the file "LICENSE" which is included in this package)
****************************************************************************/

#define DATE "31 May 2012"
#define VERSION "2.2.5"
#define VERSION_INT 20205

/* FIGlet (Frank, Ian & Glenn's Letters) */
/* by Glenn Chappell */
/* Apr 1991 */
/* Automatic file addition by Ian Chai May 1991 */
/* Punctuation and numbers addition by Ian Chai Jan 1993 */
/* Full ASCII by Glenn Chappell Feb 1993 */
/* Line-breaking, general rewrite by Glenn Chappell Mar 1993 */
/* Hard blanks by Glenn Chappell Apr 1993 */
/* Release 2.0 5 Aug 1993 */
/* Right-to-left printing, extended char set by Glenn Chappell Dec 1993 */
/* Control files by Glenn Chappell Feb 1994 */
/* Release 2.1 12 Aug 1994 */
/* Release 2.1.1 25 Aug 1994 */
/* Release 2.1.2 by Gilbert (Mad Programmer) Healton: Add -A command line 
   option.  Sept 8, 1996 */
/* Release 2.2 by John Cowan: multibyte inputs, compressed fonts,
   mapping tables, kerning/smushing options. */
/* Release 2.2.1 by Christiaan Keet: minor updates including readmes
   FAQs and comments. 13 July 2002. The new official FIGlet website is 
   http://www.figlet.org/  */
/* Release 2.2.2 by Christiaan Keet: License changed from "Artistic License"
   to "Academic Free License" as agreed by FIGlet authors. 05 July 2005 */
/* Release 2.2.3 by Claudio Matsuoka, 12 Jan 2011: BSD license, fixes */
/* Release 2.2.4 by Claudio Matsuoka, 26 Jan 2011: tlf2 font support */
/* Release 2.2.5 by Claudio Matsuoka, 31 May 2012: flc licensing, minor fixes */

/*---------------------------------------------------------------------------
  DEFAULTFONTDIR and DEFAULTFONTFILE should be defined in the Makefile.
  DEFAULTFONTDIR is the full path name of the directory in which FIGlet
    will search first for fonts (the ".flf" files).
  DEFAULTFONTFILE is the filename of the font to be used if no other
    is specified (standard.flf is recommended, but any other can be
    used). This file should reside in the directory specified by
    DEFAULTFONTDIR.
---------------------------------------------------------------------------*/
#ifndef DEFAULTFONTDIR
#define DEFAULTFONTDIR "fonts"
#endif
#ifndef DEFAULTFONTFILE
#define DEFAULTFONTFILE "standard.flf"
#endif

#include <stdio.h>
#ifdef __STDC__
#include <stdlib.h>
#endif
#include <string.h>
#include <ctype.h>
#include <sys/stat.h>
#include <fcntl.h>     /* Needed for get_columns */

#if defined(unix) || defined(__unix__) || defined(__APPLE__)
#include <unistd.h>
#include <sys/ioctl.h> /* Needed for get_columns */
#endif

#ifdef TLF_FONTS
#include <wchar.h>
#include <wctype.h>
#include "utf8.h"
#endif

#include "zipio.h"     /* Package for reading compressed files */

#define MYSTRLEN(x) ((int)strlen(x)) /* Eliminate ANSI problem */

#define DIRSEP '/'
#define DIRSEP2 '\\'
/* Leave alone for Unix and MS-DOS/Windows!
Note: '/' also used in filename in get_columns(). */

#define FONTFILESUFFIX ".flf"
#define FONTFILEMAGICNUMBER "flf2"
#define FSUFFIXLEN MYSTRLEN(FONTFILESUFFIX)
#define CONTROLFILESUFFIX ".flc"
#define CONTROLFILEMAGICNUMBER "flc2"   /* no longer used in 2.2 */
#define CSUFFIXLEN MYSTRLEN(CONTROLFILESUFFIX)
#define DEFAULTCOLUMNS 80
#define MAXLEN 255     /* Maximum character width */

/* Add support for Sam Hocevar's TOIlet fonts */
#ifdef TLF_FONTS
#define TOILETFILESUFFIX ".tlf"
#define TOILETFILEMAGICNUMBER "tlf2"
#define TSUFFIXLEN MYSTRLEN(TOILETFILESUFFIX)

int toiletfont;	/* true if font is a TOIlet TLF font */
#endif


/****************************************************************************

  Globals dealing with chars that are read

****************************************************************************/

typedef long inchr; /* "char" read from stdin */

inchr *inchrline;  /* Alloc'd inchr inchrline[inchrlinelenlimit+1]; */
                   /* Note: not null-terminated. */
int inchrlinelen,inchrlinelenlimit;
inchr deutsch[7] = {196, 214, 220, 228, 246, 252, 223};
  /* Latin-1 codes for German letters, respectively:
     LATIN CAPITAL LETTER A WITH DIAERESIS = A-umlaut
     LATIN CAPITAL LETTER O WITH DIAERESIS = O-umlaut
     LATIN CAPITAL LETTER U WITH DIAERESIS = U-umlaut
     LATIN SMALL LETTER A WITH DIAERESIS = a-umlaut
     LATIN SMALL LETTER O WITH DIAERESIS = o-umlaut
     LATIN SMALL LETTER U WITH DIAERESIS = u-umlaut
     LATIN SMALL LETTER SHARP S = ess-zed
  */

int hzmode;  /* true if reading double-bytes in HZ mode */
int gndbl[4]; /* gndbl[n] is true if Gn is double-byte */
inchr gn[4]; /* Gn character sets: ASCII, Latin-1, none, none */
int gl; /* 0-3 specifies left-half Gn character set */
int gr; /* 0-3 specifies right-half Gn character set */

int Myargc;  /* to avoid passing around argc and argv */
char **Myargv;

/****************************************************************************

  Globals dealing with chars that are written

****************************************************************************/

#ifdef TLF_FONTS
typedef wchar_t outchr; /* "char" written to stdout */
#define STRLEN(x) wcslen(x)
#define STRCPY(x,y) wcscpy((x),(y))
#define STRCAT(x,y) wcscat((x),(y))
#define ISSPACE(x) iswspace(x)
#else
typedef char outchr; /* "char" written to stdout */
#define STRLEN(x) MYSTRLEN(x)
#define STRCPY(x,y) strcpy((x),(y))
#define STRCAT(x,y) strcat((x),(y))
#define ISSPACE(x) isspace(x)
#endif

typedef struct fc {
  inchr ord;
  outchr **thechar;  /* Alloc'd char thechar[charheight][]; */
  struct fc *next;
  } fcharnode;

fcharnode *fcharlist;
outchr **currchar;
int currcharwidth;
int previouscharwidth;
outchr **outputline;   /* Alloc'd char outputline[charheight][outlinelenlimit+1]; */
int outlinelen;


/****************************************************************************

  Globals dealing with command file storage

****************************************************************************/

typedef struct cfn {
  char *thename;
  struct cfn *next;
  } cfnamenode;

cfnamenode *cfilelist,**cfilelistend;

typedef struct cm {
  int thecommand;
  inchr rangelo;
  inchr rangehi;
  inchr offset;
  struct cm *next;
  } comnode;

comnode *commandlist,**commandlistend;

/****************************************************************************

  Globals affected by command line options

****************************************************************************/

int deutschflag,justification,paragraphflag,right2left,multibyte;
int cmdinput;

#define SM_SMUSH 128
#define SM_KERN 64
#define SM_EQUAL 1
#define SM_LOWLINE 2
#define SM_HIERARCHY 4
#define SM_PAIR 8
#define SM_BIGX 16
#define SM_HARDBLANK 32

int smushmode;

#define SMO_NO 0     /* no command-line smushmode */
#define SMO_YES 1    /* use command-line smushmode, ignore font smushmode */
#define SMO_FORCE 2  /* logically OR command-line and font smushmodes */

int smushoverride;

int outputwidth;
int outlinelenlimit;
char *fontdirname,*fontname;


/****************************************************************************

  Globals read from font file

****************************************************************************/

char hardblank;
int charheight;


/****************************************************************************

  Name of program, used in error messages

****************************************************************************/

char *myname;


#ifdef TIOCGWINSZ
/****************************************************************************

  get_columns

  Determines the number of columns of /dev/tty.  Returns the number of
  columns, or -1 if error.  May return 0 if columns unknown.
  Requires include files <fcntl.h> and <sys/ioctl.h>.
  by Glenn Chappell & Ian Chai 14 Apr 1993

****************************************************************************/

int get_columns()
{
  struct winsize ws;
  int fd,result;

  if ((fd = open("/dev/tty",O_WRONLY))<0) return -1;
  result = ioctl(fd,TIOCGWINSZ,&ws);
  close(fd);
  return result?-1:ws.ws_col;
}
#endif /* ifdef TIOCGWINSZ */


/****************************************************************************

  myalloc

  Calls malloc.  If malloc returns error, prints error message and
  quits.

****************************************************************************/

#ifdef __STDC__
char *myalloc(size_t size)
#else
char *myalloc(size)
int size;
#endif
{
  char *ptr;
#ifndef __STDC__
  extern void *malloc();
#endif

  if ((ptr = (char*)malloc(size))==NULL) {
    fprintf(stderr,"%s: Out of memory\n",myname);
    exit(1);
    }
  else {
    return ptr;
    }
}


/****************************************************************************

  hasdirsep

  Returns true if s1 contains a DIRSEP or DIRSEP2 character.

****************************************************************************/

int hasdirsep(s1)
char *s1;
{
  if (strchr(s1, DIRSEP)) return 1;
  else if (strchr(s1, DIRSEP2)) return 1;
  else return 0;
}

/****************************************************************************

  suffixcmp

  Returns true if s2 is a suffix of s1; uses case-blind comparison.

****************************************************************************/

int suffixcmp(s1, s2)
char *s1;
char *s2;
{
  int len1, len2;

  len1 = MYSTRLEN(s1);
  len2 = MYSTRLEN(s2);
  if (len2 > len1) return 0;
  s1 += len1 - len2;
  while (*s1) {
    if (tolower(*s1) != tolower(*s2)) return 0;
    s1++;
    s2++;
    }
  return 1;
}
   
/****************************************************************************

  skiptoeol

  Skips to the end of a line, given a stream.  Handles \r, \n, or \r\n.

****************************************************************************/

void skiptoeol(fp)
ZFILE *fp;
{
  int dummy;

  while (dummy=Zgetc(fp),dummy!=EOF) {
    if (dummy == '\n') return;
    if (dummy == '\r') {
      dummy = Zgetc(fp);
      if (dummy != EOF && dummy != '\n') Zungetc(dummy,fp);
      return;
      }
  }
}


/****************************************************************************

  myfgets

  Local version of fgets.  Handles \r, \n, and \r\n terminators.

****************************************************************************/

char *myfgets(line,maxlen,fp)
char *line;
int maxlen;
ZFILE *fp;
{
  int c = 0;
  char *p;

  p = line;
  while((c=Zgetc(fp))!=EOF&&maxlen) {
    *p++ = c;
    maxlen--;
    if (c=='\n') break;
    if (c=='\r') {
      c = Zgetc(fp);
      if (c != EOF && c != '\n') Zungetc(c,fp);
      *(p-1) = '\n';
      break;
      }
    }
  *p = 0;
  return (c==EOF) ? NULL : line;
}


/****************************************************************************

  usageerr

  Prints "Usage: ...." line to the given stream.

****************************************************************************/

void printusage(out)
FILE *out;
{
  fprintf(out,
    "Usage: %s [ -cklnoprstvxDELNRSWX ] [ -d fontdirectory ]\n",
    myname);
  fprintf(out,
    "              [ -f fontfile ] [ -m smushmode ] [ -w outputwidth ]\n");
  fprintf(out,
    "              [ -C controlfile ] [ -I infocode ] [ message ]\n");
}


/****************************************************************************

  printinfo

  Prints version and copyright message, or utility information.

****************************************************************************/

void printinfo(infonum)
int infonum;
{
  switch (infonum) {
    case 0: /* Copyright message */
      printf("FIGlet Copyright (C) 1991-2012 Glenn Chappell, Ian Chai, ");
      printf("John Cowan,\nChristiaan Keet and Claudio Matsuoka\n");
      printf("Internet: <info@figlet.org> ");
      printf("Version: %s, date: %s\n\n",VERSION,DATE);
      printf("FIGlet, along with the various FIGlet fonts");
      printf(" and documentation, may be\n");
      printf("freely copied and distributed.\n\n");
      printf("If you use FIGlet, please send an");
      printf(" e-mail message to <info@figlet.org>.\n\n");
      printf("The latest version of FIGlet is available from the");
      printf(" web site,\n\thttp://www.figlet.org/\n\n");
      printusage(stdout);
      break;
    case 1: /* Version (integer) */
      printf("%d\n",VERSION_INT);
      break;
    case 2: /* Font directory */
      printf("%s\n",fontdirname);
      break;
    case 3: /* Font */
      printf("%s\n",fontname);
      break;
    case 4: /* Outputwidth */
      printf("%d\n",outputwidth);
      break;
    case 5: /* Font formats */
      printf("%s", FONTFILEMAGICNUMBER);
#ifdef TLF_FONTS
      printf(" %s", TOILETFILEMAGICNUMBER);
#endif
      printf("\n");
    }
}


/****************************************************************************

  readmagic

  Reads a four-character magic string from a stream.

****************************************************************************/
void readmagic(fp,magic)
ZFILE *fp;
char *magic;
{
  int i;

  for (i=0;i<4;i++) {
    magic[i] = Zgetc(fp);
    }
  magic[4] = 0;
  }
  
/****************************************************************************

  skipws

  Skips whitespace characters from a stream.

****************************************************************************/
void skipws(fp)
ZFILE *fp;
{
  int c;
  while (c=Zgetc(fp),isascii(c)&&isspace(c)) ;
  Zungetc(c,fp);
  }

/****************************************************************************

  readnum

  Reads a number from a stream.  Accepts "0" prefix for octal and
  "0x" or "0X" for hexadecimal.  Ignores leading whitespace.

****************************************************************************/
void readnum(fp,nump)
ZFILE *fp;
inchr *nump;
{
  int acc = 0;
  char *p;
  int c;
  int base;
  int sign = 1;
  char digits[] = "0123456789ABCDEF";

  skipws(fp);
  c = Zgetc(fp);
  if (c=='-') {
    sign = -1;
    }
  else {
    Zungetc(c,fp);
    }
  c = Zgetc(fp);
  if (c=='0') {
     c = Zgetc(fp);
     if (c=='x'||c=='X') {
       base = 16;
       }
     else {
       base = 8;
       Zungetc(c,fp);
       }
    }
  else {
    base = 10;
    Zungetc(c,fp);
    }

  while((c=Zgetc(fp))!=EOF) {
    c=toupper(c);
    p=strchr(digits,c);
    if (!p) {
      Zungetc(c,fp);
      *nump = acc * sign;
      return;
      }
    acc = acc*base+(p-digits);
    }
  *nump = acc * sign;
  }  

/****************************************************************************

  readTchar

  Reads a control file "T" command character specification.

  Character is a single byte, an escape sequence, or
  an escaped numeric.

****************************************************************************/

inchr readTchar(fp)
ZFILE *fp;
{
  inchr thechar;
  char next;

  thechar=Zgetc(fp);
  if (thechar=='\n' || thechar=='\r') { /* Handle badly-formatted file */
    Zungetc(thechar,fp);
    return '\0';
    }
  if (thechar!='\\') return thechar;
  next=Zgetc(fp);
  switch(next) {
    case 'a':
      return 7;
    case 'b':
      return 8;
    case 'e':
      return 27;
    case 'f':
      return 12;
    case 'n':
      return 10;
    case 'r':
      return 13;
    case 't':
      return 9;
    case 'v':
      return 11;
    default:
      if (next=='-' || next=='x' || (next>='0' && next<='9')) {
        Zungetc(next,fp);
        readnum(fp,&thechar);
        return thechar;
        }
      return next;
    }
}

/****************************************************************************

  charsetname

  Get a Tchar representing a charset name, or 0 if none available.
  Called in getcharset().

****************************************************************************/

inchr charsetname(fp)
ZFILE *fp;
{
  inchr result;

  result = readTchar(fp);
  if (result == '\n' || result == '\r') {
    result = 0;
    Zungetc(result,fp);
    }
  return result;
  }

/****************************************************************************

  charset

  Processes "g[0123]" character set specifier
  Called in readcontrol().

****************************************************************************/

void charset(n, controlfile)
int n;
ZFILE *controlfile;
{
  int ch;

  skipws(controlfile);
  if (Zgetc(controlfile) != '9') {
    skiptoeol(controlfile);
    return;
    }
  ch = Zgetc(controlfile);
  if (ch == '6') {
     gn[n] = 65536L * charsetname(controlfile) + 0x80;
     gndbl[n] = 0;
     skiptoeol(controlfile);
     return;
     }
  if (ch != '4') {
    skiptoeol(controlfile);
    return;
    }
  ch = Zgetc(controlfile);
  if (ch == 'x') {
     if (Zgetc(controlfile) != '9') {
       skiptoeol(controlfile);
       return;
       }
     if (Zgetc(controlfile) != '4') {
       skiptoeol(controlfile);
       return;
       }
     skipws(controlfile);
     gn[n] = 65536L * charsetname(controlfile);
     gndbl[n] = 1;
     skiptoeol(controlfile);
     return;
     }
  Zungetc(ch, controlfile);
  skipws(controlfile);
  gn[n] = 65536L * charsetname(controlfile);
  gndbl[n] = 0;
  return;
  }

/****************************************************************************

  FIGopen

  Given a FIGlet font or control file name and suffix, return the file
  or NULL if not found

****************************************************************************/

ZFILE *FIGopen(name,suffix)
char *name;
char *suffix;
{
  char *fontpath;
  ZFILE *fontfile;
  struct stat st;
  int namelen;

  namelen = MYSTRLEN(fontdirname);
  fontpath = (char*)alloca(sizeof(char)*
    (namelen+MYSTRLEN(name)+MYSTRLEN(suffix)+2));
  fontfile = NULL;
  if (!hasdirsep(name)) {  /* not a full path name */
    strcpy(fontpath,fontdirname);
    fontpath[namelen] = DIRSEP;
    fontpath[namelen+1] = '\0';
    strcat(fontpath,name);
    strcat(fontpath,suffix);
    if(stat(fontpath,&st)==0) goto ok;
    }
  /* just append suffix */
  strcpy(fontpath,name);
  strcat(fontpath,suffix);
  if(stat(fontpath,&st)==0) goto ok;

  return NULL;

ok:
  fontfile = Zopen(fontpath,"rb");
  return fontfile;
}

/****************************************************************************

  readcontrol

  Allocates memory and reads in the given control file.
  Called in readcontrolfiles().

****************************************************************************/

void readcontrol(controlname)
char *controlname;
{
  inchr firstch,lastch;
  char dashcheck;
  inchr offset;
  int command;
  ZFILE *controlfile;

  controlfile = FIGopen(controlname,CONTROLFILESUFFIX);

  if (controlfile==NULL) {
    fprintf(stderr,"%s: %s: Unable to open control file\n",myname,
      controlname);
    exit(1);
    }

  (*commandlistend) = (comnode*)myalloc(sizeof(comnode));
  (*commandlistend)->thecommand = 0; /* Begin with a freeze command */
  commandlistend = &(*commandlistend)->next;
  (*commandlistend) = NULL;

  while(command=Zgetc(controlfile),command!=EOF) {
    switch (command) {
      case 't': /* Translate */
        skipws(controlfile);
        firstch=readTchar(controlfile);
        if ((dashcheck=Zgetc(controlfile))=='-') {
          lastch=readTchar(controlfile);
          }
        else {
          Zungetc(dashcheck,controlfile);
          lastch=firstch;
          }
        skipws(controlfile);
        offset=readTchar(controlfile)-firstch;
        skiptoeol(controlfile);
        (*commandlistend) = (comnode*)myalloc(sizeof(comnode));
        (*commandlistend)->thecommand = 1;
        (*commandlistend)->rangelo = firstch;
        (*commandlistend)->rangehi = lastch;
        (*commandlistend)->offset = offset;
        commandlistend = &(*commandlistend)->next;
        (*commandlistend) = NULL;
        break;
      case '0': case '1': case '2': case '3': case '4':
      case '5': case '6': case '7': case '8': case '9':
      case '-':
                /* Mapping table entry */
        Zungetc(command,controlfile);
        readnum(controlfile,&firstch);
        skipws(controlfile);
	readnum(controlfile,&lastch);
	offset=lastch-firstch;
        lastch=firstch;
        skiptoeol(controlfile);
        (*commandlistend) = (comnode*)myalloc(sizeof(comnode));
        (*commandlistend)->thecommand = 1;
        (*commandlistend)->rangelo = firstch;
        (*commandlistend)->rangehi = lastch;
        (*commandlistend)->offset = offset;
        commandlistend = &(*commandlistend)->next;
        (*commandlistend) = NULL;
        break;
      case 'f': /* freeze */
        skiptoeol(controlfile);
        (*commandlistend) = (comnode*)myalloc(sizeof(comnode));
        (*commandlistend)->thecommand = 0;
        commandlistend = &(*commandlistend)->next;
        (*commandlistend) = NULL;
        break;
      case 'b': /* DBCS input mode */
        multibyte = 1;
        break;
      case 'u': /* UTF-8 input mode */
        multibyte = 2;
        break;
      case 'h': /* HZ input mode */
        multibyte = 3;
        break;
      case 'j': /* Shift-JIS input mode */
        multibyte = 4;
        break;
      case 'g': /* ISO 2022 character set choices */
        multibyte = 0;
        skipws(controlfile);
        command=Zgetc(controlfile);
        switch (command) {
          case '0': /* define G0 charset */
            charset(0, controlfile);
            break;
          case '1': /* set G1 charset */
            charset(1, controlfile);
            break;
          case '2': /* set G2 charset */
            charset(2, controlfile);
            break;
          case '3': /* set G3 charset */
            charset(3, controlfile);
            break;
          case 'l': case 'L': /* define left half */
            skipws(controlfile);
            gl = Zgetc(controlfile) - '0';
            skiptoeol(controlfile);
            break;
          case 'r': case 'R': /* define right half */
            skipws(controlfile);
            gr = Zgetc(controlfile) - '0';
            skiptoeol(controlfile);
            break;
          default: /* meaningless "g" command */
            skiptoeol(controlfile);
          }
      case '\r': case '\n': /* blank line */
        break;
      default: /* Includes '#' */
        skiptoeol(controlfile);
      }
    }
  Zclose(controlfile);
}


/****************************************************************************

  readcontrolfiles

  Reads in the controlfiles names in cfilelist.  Uses readcontrol.
  Called in main().

****************************************************************************/

void readcontrolfiles()
{
  cfnamenode *cfnptr;

  for (cfnptr=cfilelist;cfnptr!=NULL;cfnptr=cfnptr->next) {
    readcontrol(cfnptr->thename);
    }
}


/****************************************************************************

  clearcfilelist

  Clears the control file list.  Assumes thename does not need freeing.

****************************************************************************/

void clearcfilelist()
{
  cfnamenode *cfnptr1,*cfnptr2;

  cfnptr1 = cfilelist;
  while (cfnptr1 != NULL) {
    cfnptr2 = cfnptr1->next;
    free(cfnptr1);
    cfnptr1 = cfnptr2;
    }
  cfilelist = NULL;
  cfilelistend = &cfilelist;
}


/****************************************************************************

  getparams

  Handles all command-line parameters.  Puts all parameters within
  bounds.

****************************************************************************/

void getparams()
{
  extern char *optarg;
  extern int optind;
  int c; /* "Should" be a char -- need int for "!= -1" test*/
  int columns,infoprint;
  char *controlname,*env;

  if ((myname = strrchr(Myargv[0],DIRSEP))!=NULL) {
    myname++;
    }
  else {
    myname = Myargv[0];
    }
  fontdirname = DEFAULTFONTDIR;
  env = getenv("FIGLET_FONTDIR");
  if (env!=NULL) {
    fontdirname = env;
    }
  fontname = DEFAULTFONTFILE;
  cfilelist = NULL;
  cfilelistend = &cfilelist;
  commandlist = NULL;
  commandlistend = &commandlist;
  smushoverride = SMO_NO;
  deutschflag = 0;
  justification = -1;
  right2left = -1;
  paragraphflag = 0;
  infoprint = -1;
  cmdinput = 0;
  outputwidth = DEFAULTCOLUMNS;
  gn[1] = 0x80;
  gr = 1;
  while ((c = getopt(Myargc,Myargv,"ADEXLRI:xlcrpntvm:w:d:f:C:NFskSWo"))!= -1) {
      /* Note: -F is not a legal option -- prints a special err message.  */
    switch (c) {
      case 'A':
        cmdinput = 1;
        break;
      case 'D':
        deutschflag = 1;
        break;
      case 'E':
        deutschflag = 0;
        break;
      case 'X':
        right2left = -1;
        break;
      case 'L':
        right2left = 0;
        break;
      case 'R':
        right2left = 1;
        break;
      case 'x':
        justification = -1;
        break;
      case 'l':
        justification = 0;
        break;
      case 'c':
        justification = 1;
        break;
      case 'r':
        justification = 2;
        break;
      case 'p':
        paragraphflag = 1;
        break;
      case 'n':
        paragraphflag = 0;
        break;
      case 's':
        smushoverride = SMO_NO;
        break;
      case 'k':
        smushmode = SM_KERN;
        smushoverride = SMO_YES;
        break;
      case 'S':
        smushmode = SM_SMUSH;
	smushoverride = SMO_FORCE;
        break;
      case 'o':
        smushmode = SM_SMUSH;
	smushoverride = SMO_YES;
        break;
      case 'W':
        smushmode = 0;
	smushoverride = SMO_YES;
        break;
      case 't':
#ifdef TIOCGWINSZ
        columns = get_columns();
        if (columns>0) {
          outputwidth = columns;
          }
#else /* ifdef TIOCGWINSZ */
        fprintf(stderr,
          "%s: \"-t\" is disabled, since ioctl is not fully implemented.\n",
          myname);
#endif /* ifdef TIOCGWINSZ */
        break;
      case 'v':
        infoprint = 0;
        break;
      case 'I':
        infoprint = atoi(optarg);
        break;
      case 'm':
        smushmode = atoi(optarg);
        if (smushmode < -1) {
          smushoverride = SMO_NO;
          break;
          }
	if (smushmode == 0) smushmode = SM_KERN;
	else if (smushmode == -1) smushmode = 0;
	else smushmode = (smushmode & 63) | SM_SMUSH;
	smushoverride = SMO_YES;
        break;
      case 'w':
        columns = atoi(optarg);
        if (columns>0) {
          outputwidth = columns;
          }
        break;
      case 'd':
        fontdirname = optarg;
        break;
      case 'f':
        fontname = optarg;
        if (suffixcmp(fontname,FONTFILESUFFIX)) {
          fontname[MYSTRLEN(fontname)-FSUFFIXLEN] = '\0';
          }
#ifdef TLF_FONTS
        else if (suffixcmp(fontname,TOILETFILESUFFIX)) {
          fontname[MYSTRLEN(fontname)-TSUFFIXLEN] = '\0';
          }
#endif
        break;
      case 'C':
        controlname = optarg;
        if (suffixcmp(controlname, CONTROLFILESUFFIX)) {
          controlname[MYSTRLEN(controlname)-CSUFFIXLEN] = '\0';
          }
        (*cfilelistend) = (cfnamenode*)myalloc(sizeof(cfnamenode));
        (*cfilelistend)->thename = controlname;
        cfilelistend = &(*cfilelistend)->next;
        (*cfilelistend) = NULL;
        break;
      case 'N':
        clearcfilelist();
        multibyte = 0;
        gn[0] = 0;
        gn[1] = 0x80;
        gn[2] = gn[3] = 0;
        gndbl[0] = gndbl[1] = gndbl[2] = gndbl[3] = 0;
        gl = 0;
        gr = 1;
        break;
      case 'F': /* Not a legal option */
        fprintf(stderr,"%s: illegal option -- F\n",myname);
        printusage(stderr);
        fprintf(stderr,"\nBecause of numerous incompatibilities, the");
        fprintf(stderr," \"-F\" option has been\n");
        fprintf(stderr,"removed.  It has been replaced by the \"figlist\"");
        fprintf(stderr," program, which is now\n");
        fprintf(stderr,"included in the basic FIGlet package.  \"figlist\"");
        fprintf(stderr," is also available\n");
        fprintf(stderr,"from  http://www.figlet.org/");
        fprintf(stderr,"under UNIX utilities.\n");
        exit(1);
        break;
      default:
        printusage(stderr);
        exit(1);
      }
    }
  if (optind!=Myargc) cmdinput = 1; /* force cmdinput if more arguments */
  outlinelenlimit = outputwidth-1;
  if (infoprint>=0) {
    printinfo(infoprint);
    exit(0);
    }
}


/****************************************************************************

  clearline

  Clears both the input (inchrline) and output (outputline) storage.

****************************************************************************/

void clearline()
{
  int i;

  for (i=0;i<charheight;i++) {
    outputline[i][0] = '\0';
    }
  outlinelen = 0;
  inchrlinelen = 0;
}


/****************************************************************************

  readfontchar

  Reads a font character from the font file, and places it in a
  newly-allocated entry in the list.

****************************************************************************/

void readfontchar(file,theord)
ZFILE *file;
inchr theord;
{
  int row,k;
  char templine[MAXLEN+1];
  outchr endchar, outline[MAXLEN+1];
  fcharnode *fclsave;

  fclsave = fcharlist;
  fcharlist = (fcharnode*)myalloc(sizeof(fcharnode));
  fcharlist->ord = theord;
  fcharlist->thechar = (outchr**)myalloc(sizeof(outchr*)*charheight);
  fcharlist->next = fclsave;

  outline[0] = 0;

  for (row=0;row<charheight;row++) {
    if (myfgets(templine,MAXLEN,file)==NULL) {
      templine[0] = '\0';
      }
#ifdef TLF_FONTS
    utf8_to_wchar(templine,MAXLEN,outline,MAXLEN,0);
#else
    strcpy(outline,templine);
#endif
    k = STRLEN(outline)-1;
    while (k>=0 && ISSPACE(outline[k])) {  /* remove trailing spaces */
      k--;
      }
    if (k>=0) {
      endchar = outline[k];  /* remove endmarks */
      while (k>=0 && outline[k]==endchar) {
        k--;
        }
      }
    outline[k+1] = '\0';
    fcharlist->thechar[row] = (outchr*)myalloc(sizeof(outchr)*(STRLEN(outline)+1));
    STRCPY(fcharlist->thechar[row],outline);
    }
}


/****************************************************************************

  readfont

  Allocates memory, initializes variables, and reads in the font.
  Called near beginning of main().

****************************************************************************/

void readfont()
{
  int i,row,numsread;
  inchr theord;
  int maxlen,cmtlines,ffright2left;
  int smush,smush2;
  char fileline[MAXLEN+1],magicnum[5];
  ZFILE *fontfile;

  fontfile = FIGopen(fontname,FONTFILESUFFIX);
#ifdef TLF_FONTS
  if (fontfile==NULL) {
    fontfile = FIGopen(fontname,TOILETFILESUFFIX);
    if(fontfile) toiletfont = 1;
    }
#endif

  if (fontfile==NULL) {
    fprintf(stderr,"%s: %s: Unable to open font file\n",myname,fontname);
    exit(1);
    }

  readmagic(fontfile,magicnum);
  if (myfgets(fileline,MAXLEN,fontfile)==NULL) {
    fileline[0] = '\0';
    }
  if (MYSTRLEN(fileline)>0 ? fileline[MYSTRLEN(fileline)-1]!='\n' : 0) {
    skiptoeol(fontfile);
    }
  numsread = sscanf(fileline,"%*c%c %d %*d %d %d %d %d %d",
    &hardblank,&charheight,&maxlen,&smush,&cmtlines,
    &ffright2left,&smush2);

  if (maxlen > MAXLEN) {
    fprintf(stderr,"%s: %s: character is too wide\n",myname,fontname);
    exit(1);
    }
#ifdef TLF_FONTS
  if ((!toiletfont && strcmp(magicnum,FONTFILEMAGICNUMBER)) ||
      (toiletfont && strcmp(magicnum,TOILETFILEMAGICNUMBER)) || numsread<5) {
#else
  if (strcmp(magicnum,FONTFILEMAGICNUMBER) || numsread<5) {
#endif
    fprintf(stderr,"%s: %s: Not a FIGlet 2 font file\n",myname,fontname);
    exit(1);
    }
  for (i=1;i<=cmtlines;i++) {
    skiptoeol(fontfile);
    }

  if (numsread<6) {
    ffright2left = 0;
    }

  if (numsread<7) { /* if no smush2, decode smush into smush2 */
    if (smush == 0) smush2 = SM_KERN;
    else if (smush < 0) smush2 = 0;
    else smush2 = (smush & 31) | SM_SMUSH;
    }

  if (charheight<1) {
    charheight = 1;
    }

  if (maxlen<1) {
    maxlen = 1;
    }

  maxlen += 100; /* Give ourselves some extra room */

  if (smushoverride == SMO_NO)
     smushmode = smush2;
  else if (smushoverride == SMO_FORCE)
     smushmode |= smush2;

  if (right2left<0) {
    right2left = ffright2left;
    }

  if (justification<0) {
    justification = 2*right2left;
    }

  /* Allocate "missing" character */
  fcharlist = (fcharnode*)myalloc(sizeof(fcharnode));
  fcharlist->ord = 0;
  fcharlist->thechar = (outchr**)myalloc(sizeof(outchr*)*charheight);
  fcharlist->next = NULL;
  for (row=0;row<charheight;row++) {
    fcharlist->thechar[row] = (outchr*)myalloc(sizeof(outchr));
    fcharlist->thechar[row][0] = '\0';
    }
  for (theord=' ';theord<='~';theord++) {
    readfontchar(fontfile,theord);
    }
  for (theord=0;theord<=6;theord++) {
    readfontchar(fontfile,deutsch[theord]);
    }
  while (myfgets(fileline,maxlen+1,fontfile)==NULL?0:
    sscanf(fileline,"%li",&theord)==1) {
    readfontchar(fontfile,theord);
    }
  Zclose(fontfile);
}


/****************************************************************************

  linealloc

  Allocates & clears outputline, inchrline. Sets inchrlinelenlimit.
  Called near beginning of main().

****************************************************************************/

void linealloc()
{
  int row; 

  outputline = (outchr**)myalloc(sizeof(outchr*)*charheight);
  for (row=0;row<charheight;row++) {
    outputline[row] = (outchr*)myalloc(sizeof(outchr)*(outlinelenlimit+1));
    }
  inchrlinelenlimit = outputwidth*4+100;
  inchrline = (inchr*)myalloc(sizeof(inchr)*(inchrlinelenlimit+1));
  clearline();
}


/****************************************************************************

  getletter

  Sets currchar to point to the font entry for the given character.
  Sets currcharwidth to the width of this character.

****************************************************************************/

void getletter(c)
inchr c;
{
  fcharnode *charptr;

  for (charptr=fcharlist;charptr==NULL?0:charptr->ord!=c;
    charptr=charptr->next) ;
  if (charptr!=NULL) {
    currchar = charptr->thechar;
    }
  else {
    for (charptr=fcharlist;charptr==NULL?0:charptr->ord!=0;
      charptr=charptr->next) ;
    currchar = charptr->thechar;
    }
  previouscharwidth = currcharwidth;
  currcharwidth = STRLEN(currchar[0]);
}


/****************************************************************************

  smushem

  Given 2 characters, attempts to smush them into 1, according to
  smushmode.  Returns smushed character or '\0' if no smushing can be
  done.

  smushmode values are sum of following (all values smush blanks):
    1: Smush equal chars (not hardblanks)
    2: Smush '_' with any char in hierarchy below
    4: hierarchy: "|", "/\", "[]", "{}", "()", "<>"
       Each class in hier. can be replaced by later class.
    8: [ + ] -> |, { + } -> |, ( + ) -> |
   16: / + \ -> X, > + < -> X (only in that order)
   32: hardblank + hardblank -> hardblank

****************************************************************************/

outchr smushem(lch,rch)
outchr lch,rch;
{
  if (lch==' ') return rch;
  if (rch==' ') return lch;

  if (previouscharwidth<2 || currcharwidth<2) return '\0';
    /* Disallows overlapping if the previous character */
    /* or the current character has a width of 1 or zero. */

  if ((smushmode & SM_SMUSH) == 0) return '\0';  /* kerning */

  if ((smushmode & 63) == 0) {
    /* This is smushing by universal overlapping. */
    if (lch==' ') return rch;
    if (rch==' ') return lch;
    if (lch==hardblank) return rch;
    if (rch==hardblank) return lch;
      /* Above four lines ensure overlapping preference to */
      /* visible characters. */
    if (right2left==1) return lch;
      /* Above line ensures that the dominant (foreground) */
      /* fig-character for overlapping is the latter in the */
      /* user's text, not necessarily the rightmost character. */
    return rch;
      /* Occurs in the absence of above exceptions. */
    }
  
  if (smushmode & SM_HARDBLANK) {
    if (lch==hardblank && rch==hardblank) return lch;
    }

  if (lch==hardblank || rch==hardblank) return '\0';

  if (smushmode & SM_EQUAL) {
    if (lch==rch) return lch;
    }

  if (smushmode & SM_LOWLINE) {
    if (lch=='_' && strchr("|/\\[]{}()<>",rch)) return rch;
    if (rch=='_' && strchr("|/\\[]{}()<>",lch)) return lch;
    }

  if (smushmode & SM_HIERARCHY) {
    if (lch=='|' && strchr("/\\[]{}()<>",rch)) return rch;
    if (rch=='|' && strchr("/\\[]{}()<>",lch)) return lch;
    if (strchr("/\\",lch) && strchr("[]{}()<>",rch)) return rch;
    if (strchr("/\\",rch) && strchr("[]{}()<>",lch)) return lch;
    if (strchr("[]",lch) && strchr("{}()<>",rch)) return rch;
    if (strchr("[]",rch) && strchr("{}()<>",lch)) return lch;
    if (strchr("{}",lch) && strchr("()<>",rch)) return rch;
    if (strchr("{}",rch) && strchr("()<>",lch)) return lch;
    if (strchr("()",lch) && strchr("<>",rch)) return rch;
    if (strchr("()",rch) && strchr("<>",lch)) return lch;
    }

  if (smushmode & SM_PAIR) {
    if (lch=='[' && rch==']') return '|';
    if (rch=='[' && lch==']') return '|';
    if (lch=='{' && rch=='}') return '|';
    if (rch=='{' && lch=='}') return '|';
    if (lch=='(' && rch==')') return '|';
    if (rch=='(' && lch==')') return '|';
    }

  if (smushmode & SM_BIGX) {
    if (lch=='/' && rch=='\\') return '|';
    if (rch=='/' && lch=='\\') return 'Y';
    if (lch=='>' && rch=='<') return 'X';
      /* Don't want the reverse of above to give 'X'. */
    }

  return '\0';
}


/****************************************************************************

  smushamt

  Returns the maximum amount that the current character can be smushed
  into the current line.

****************************************************************************/

int smushamt()
{
  int maxsmush,amt;
  int row,linebd,charbd;
  outchr ch1,ch2;

  if ((smushmode & (SM_SMUSH | SM_KERN)) == 0) {
    return 0;
    }
  maxsmush = currcharwidth;
  for (row=0;row<charheight;row++) {
    if (right2left) {
      if (maxsmush>STRLEN(outputline[row])) {
        maxsmush=STRLEN(outputline[row]);
        }
      for (charbd=STRLEN(currchar[row]);
        ch1=currchar[row][charbd],(charbd>0&&(!ch1||ch1==' '));charbd--) ;
      for (linebd=0;ch2=outputline[row][linebd],ch2==' ';linebd++) ;
      amt = linebd+currcharwidth-1-charbd;
      }
    else {
      for (linebd=STRLEN(outputline[row]);
        ch1 = outputline[row][linebd],(linebd>0&&(!ch1||ch1==' '));linebd--) ;
      for (charbd=0;ch2=currchar[row][charbd],ch2==' ';charbd++) ;
      amt = charbd+outlinelen-1-linebd;
      }
    if (!ch1||ch1==' ') {
      amt++;
      }
    else if (ch2) {
      if (smushem(ch1,ch2)!='\0') {
        amt++;
        }
      }
    if (amt<maxsmush) {
      maxsmush = amt;
      }
    }
  return maxsmush;
}


/****************************************************************************

  addchar

  Attempts to add the given character onto the end of the current line.
  Returns 1 if this can be done, 0 otherwise.

****************************************************************************/

int addchar(c)
inchr c;
{
  int smushamount,row,k,column;
  outchr *templine;

  getletter(c);
  smushamount = smushamt();
  if (outlinelen+currcharwidth-smushamount>outlinelenlimit
      ||inchrlinelen+1>inchrlinelenlimit) {
    return 0;
    }

  templine = (outchr*)myalloc(sizeof(outchr)*(outlinelenlimit+1));
  for (row=0;row<charheight;row++) {
    if (right2left) {
      STRCPY(templine,currchar[row]);
      for (k=0;k<smushamount;k++) {
        templine[currcharwidth-smushamount+k] =
          smushem(templine[currcharwidth-smushamount+k],outputline[row][k]);
        }
      STRCAT(templine,outputline[row]+smushamount);
      STRCPY(outputline[row],templine);
      }
    else {
      for (k=0;k<smushamount;k++) {
	column = outlinelen-smushamount+k;
	if (column < 0) {
	  column = 0;
	  }
        outputline[row][column] =
          smushem(outputline[row][column],currchar[row][k]);
        }
      STRCAT(outputline[row],currchar[row]+smushamount);
      }
    }
  free(templine);
  outlinelen = STRLEN(outputline[0]);
  inchrline[inchrlinelen++] = c;
  return 1;
}


/****************************************************************************

  putstring

  Prints out the given null-terminated string, substituting blanks
  for hardblanks.  If outputwidth is 1, prints the entire string;
  otherwise prints at most outputwidth-1 characters.  Prints a newline
  at the end of the string.  The string is left-justified, centered or
  right-justified (taking outputwidth as the screen width) if
  justification is 0, 1 or 2, respectively.

****************************************************************************/

void putstring(string)
outchr *string;
{
  int i,len;
  char c[10];
#ifdef TLF_FONTS
  size_t size;
  wchar_t wc[2];
#endif

  len = STRLEN(string);
  if (outputwidth>1) {
    if (len>outputwidth-1) {
      len = outputwidth-1;
      }
    if (justification>0) {
      for (i=1;(3-justification)*i+len+justification-2<outputwidth;i++) {
        putchar(' ');
        }
      }
    }
  for (i=0;i<len;i++) {
#ifdef TLF_FONTS
    wc[0] = string[i];
    wc[1] = 0;
    size = wchar_to_utf8(wc,1,c,10,0);
    if(size==1) {
      if(c[0]==hardblank) {
        c[0] = ' ';
        }
      }
    c[size] = 0;
    printf("%s",c);
#else
    putchar(string[i]==hardblank?' ':string[i]);
#endif
    }
  putchar('\n');
}


/****************************************************************************

  printline

  Prints outputline using putstring, then clears the current line.

****************************************************************************/

void printline()
{
  int i;

  for (i=0;i<charheight;i++) {
    putstring(outputline[i]);
    }
  clearline();
}


/****************************************************************************

  splitline

  Splits inchrline at the last word break (bunch of consecutive blanks).
  Makes a new line out of the first part and prints it using
  printline.  Makes a new line out of the second part and returns.

****************************************************************************/

void splitline()
{
  int i,gotspace,lastspace,len1,len2;
  inchr *part1,*part2;

  part1 = (inchr*)myalloc(sizeof(inchr)*(inchrlinelen+1));
  part2 = (inchr*)myalloc(sizeof(inchr)*(inchrlinelen+1));
  gotspace = 0;
  lastspace = inchrlinelen-1;
  for (i=inchrlinelen-1;i>=0;i--) {
    if (!gotspace && inchrline[i]==' ') {
      gotspace = 1;
      lastspace = i;
      }
    if (gotspace && inchrline[i]!=' ') {
      break;
      }
    }
  len1 = i+1;
  len2 = inchrlinelen-lastspace-1;
  for (i=0;i<len1;i++) {
    part1[i] = inchrline[i];
    }
  for (i=0;i<len2;i++) {
    part2[i] = inchrline[lastspace+1+i];
    }
  clearline();
  for (i=0;i<len1;i++) {
    addchar(part1[i]);
    }
  printline();
  for (i=0;i<len2;i++) {
    addchar(part2[i]);
    }
  free(part1);
  free(part2);
}


/****************************************************************************

  handlemapping

  Given an input character (type inchr), executes re-mapping commands
  read from control files.  Returns re-mapped character (inchr).

****************************************************************************/

inchr handlemapping(c)
inchr c;
{
  comnode *cmptr;

  cmptr=commandlist;
  while (cmptr!=NULL) {
    if (cmptr->thecommand ?
      (c >= cmptr->rangelo && c <= cmptr->rangehi) : 0) {
      c += cmptr->offset;
      while(cmptr!=NULL ? cmptr->thecommand : 0) {
        cmptr=cmptr->next;
        }
      }
    else {
      cmptr=cmptr->next;
      }
    }
  return c;
}

/****************************************************************************

  Agetchar

  Replacement to getchar().
  Acts exactly like getchar if -A is NOT specified,
  else obtains input from All remaining command line words.

****************************************************************************/

int Agetchar()
{
    extern int optind;		/* current argv[] element under study */
    static int AgetMode = 0;	/* >= 0 for displacement into argv[n], <0 EOF */
    char   *arg;		/* pointer to active character */
    int    c;			/* current character */

    if ( ! cmdinput )		/* is -A active? */
	return( getchar() );	/* no: return stdin character */

    if ( AgetMode < 0 || optind >= Myargc )		/* EOF is sticky: */
	return( EOF );		/* **ensure it now and forever more */

    /* find next character */
    arg = Myargv[optind];		/* pointer to active arg */
    c = arg[AgetMode++]&0xFF;	/* get appropriate char of arg */

    if ( ! c )			/* at '\0' that terminates word? */
    {   /* at end of word: return ' ' if normal word, '\n' if empty */
	c = ' ';		/* suppose normal word and return blank */
	if ( AgetMode == 1 )	/* if ran out in very 1st char, force \n */
	    c = '\n';		/* (allows "hello '' world" to do \n at '') */
	AgetMode = 0;		/* return to char 0 in NEXT word */
	if ( ++optind >= Myargc )	/* run up word count and check if at "EOF" */
	{   /* just ran out of arguments */
	    c = EOF;		/* return EOF */
	    AgetMode = -1;	/* ensure all future returns return EOF */
	}
    }

    return( c );		/* return appropriate character */

}	/* end: Agetchar() */


/****************************************************************************

  iso2022

  Called by getinchr.  Interprets ISO 2022 sequences

******************************************************************************/

inchr iso2022()
{
  inchr ch;
  inchr ch2;
  int save_gl;
  int save_gr;

  ch = Agetchar();
  if (ch == EOF) return ch;
  if (ch == 27) ch = Agetchar() + 0x100; /* ESC x */
  if (ch == 0x100 + '$') ch = Agetchar() + 0x200; /* ESC $ x */
  switch (ch) {
    case 14: /* invoke G1 into GL */
      gl = 1;
      return iso2022();
    case 15: /* invoke G0 into GL */
      gl = 0;
      return iso2022();
    case 142: case 'N' + 0x100: /* invoke G2 into GL for next char */
      save_gl = gl; save_gr = gr;
      gl = gr = 2;
      ch = iso2022();
      gl = save_gl; gr = save_gr;
      return ch;
    case 143: case 'O' + 0x100: /* invoke G3 into GL for next char */
      save_gl = gl; save_gr = gr;
      gl = gr = 3;
      ch = iso2022();
      gl = save_gl; gr = save_gr;
      return ch;
    case 'n' + 0x100: /* invoke G2 into GL */
      gl = 2;
      return iso2022();
    case 'o' + 0x100: /* invoke G3 into GL */
      gl = 3;
      return iso2022();
    case '~' + 0x100: /* invoke G1 into GR */
      gr = 1;
      return iso2022();
    case '}' + 0x100: /* invoke G2 into GR */
      gr = 2;
      return iso2022();
    case '|' + 0x100: /* invoke G3 into GR */
      gr = 3;
      return iso2022();
    case '(' + 0x100: /* set G0 to 94-char set */
      ch = Agetchar();
      if (ch == 'B') ch = 0; /* ASCII */
      gn[0] = ch << 16;
      gndbl[0] = 0;
      return iso2022();
    case ')' + 0x100: /* set G1 to 94-char set */
      ch = Agetchar();
      if (ch == 'B') ch = 0;
      gn[1] = ch << 16;
      gndbl[1] = 0;
      return iso2022();
    case '*' + 0x100: /* set G2 to 94-char set */
      ch = Agetchar();
      if (ch == 'B') ch = 0;
      gn[2] = ch << 16;
      gndbl[2] = 0;
      return iso2022();
    case '+' + 0x100: /* set G3 to 94-char set */
      ch = Agetchar();
      if (ch == 'B') ch = 0;
      gn[3] = ch << 16;
      gndbl[3] = 0;
      return iso2022();
    case '-' + 0x100: /* set G1 to 96-char set */
      ch = Agetchar();
      if (ch == 'A') ch = 0; /* Latin-1 top half */
      gn[1] = (ch << 16) | 0x80;
      gndbl[1] = 0;
      return iso2022();
    case '.' + 0x100: /* set G2 to 96-char set */
      ch = Agetchar();
      if (ch == 'A') ch = 0;
      gn[2] = (ch << 16) | 0x80;
      gndbl[2] = 0;
      return iso2022();
    case '/' + 0x100: /* set G3 to 96-char set */
      ch = Agetchar();
      if (ch == 'A') ch = 0;
      gn[3] = (ch << 16) | 0x80;
      gndbl[3] = 0;
      return iso2022();
    case '(' + 0x200: /* set G0 to 94 x 94 char set */
      ch = Agetchar();
      gn[0] = ch << 16;
      gndbl[0] = 1;
      return iso2022();
    case ')' + 0x200: /* set G1 to 94 x 94 char set */
      ch = Agetchar();
      gn[1] = ch << 16;
      gndbl[1] = 1;
      return iso2022();
    case '*' + 0x200: /* set G2 to 94 x 94 char set */
      ch = Agetchar();
      gn[2] = ch << 16;
      gndbl[2] = 1;
      return iso2022();
    case '+' + 0x200: /* set G3 to 94 x 94 char set */
      ch = Agetchar();
      gn[3] = ch << 16;
      gndbl[3] = 1;
      return iso2022();
    default:
      if (ch & 0x200) { /* set G0 to 94 x 94 char set (deprecated) */
        gn[0] = (ch & ~0x200) << 16;
        gndbl[0] = 1;
        return iso2022();
        }
      }

  if (ch >= 0x21 && ch <= 0x7E) { /* process GL */
    if (gndbl[gl]) {
      ch2 = Agetchar();
      return gn[gl] | (ch << 8) | ch2;
      }
    else return gn[gl] | ch;
    }
  else if (ch >= 0xA0 && ch <= 0xFF) { /* process GR */
    if (gndbl[gr]) {
      ch2 = Agetchar();
      return gn[gr] | (ch << 8) | ch2;
      }
    else return gn[gr] | (ch & ~0x80);
    }
  else return ch;
  }

/****************************************************************************

  ungetinchr

  Called by main.  Pushes back an "inchr" to be read by getinchr
  on the next call.

******************************************************************************/
inchr getinchr_buffer;
int getinchr_flag;

inchr ungetinchr(c)
inchr c;
{
  getinchr_buffer = c;
  getinchr_flag = 1;
  return c;
}

/*****************************************************************************

  getinchr

  Called by main.  Processes multibyte characters.  Invokes Agetchar.
  If multibyte = 0, ISO 2022 mode (see iso2022 routine).
  If multibyte = 1,  double-byte mode (0x00-0x7f bytes are characters,
    0x80-0xFF bytes are first byte of a double-byte character).
  If multibyte = 2, Unicode UTF-8 mode (0x00-0x7F bytes are characters,
    0x80-0xBF bytes are nonfirst byte of a multibyte character,
    0xC0-0xFD bytes are first byte of a multibyte character,
    0xFE-0xFF bytes are errors (all errors return code 0x0080)).
  If multibyte = 3, HZ mode ("~{" starts double-byte mode, "}~" ends it,
    "~~" is a tilde, "~x" for all other x is ignored).
  If multibyte = 4, Shift-JIS mode (0x80-0x9F and 0xE0-0xEF are first byte
    of a double-byte character, all other bytes are characters).
 

*****************************************************************************/

inchr getinchr()
{
  int ch, ch2, ch3, ch4, ch5, ch6;

  if (getinchr_flag) {
    getinchr_flag = 0;
    return getinchr_buffer;
    }

  switch(multibyte) {
    case 0: /* single-byte */
      return iso2022();
   case 1: /* DBCS */
     ch = Agetchar();
     if ((ch >= 0x80 && ch <= 0x9F) ||
         (ch >= 0xE0 && ch <= 0xEF)) {
       ch = (ch << 8) + Agetchar();
       }
     return ch;
   case 2: /* UTF-8 */
      ch = Agetchar();
      if (ch < 0x80) return ch;  /* handles EOF, too */
      if (ch < 0xC0 || ch > 0xFD)
        return 0x0080;  /* illegal first character */
      ch2 = Agetchar() & 0x3F;
      if (ch < 0xE0) return ((ch & 0x1F) << 6) + ch2;
      ch3 = Agetchar() & 0x3F;
      if (ch < 0xF0)
        return ((ch & 0x0F) << 12) + (ch2 << 6) + ch3;
      ch4 = Agetchar() & 0x3F;
      if (ch < 0xF8)
        return ((ch & 0x07) << 18) + (ch2 << 12) + (ch3 << 6) + ch4;
      ch5 = Agetchar() & 0x3F;
      if (ch < 0xFC)
        return ((ch & 0x03) << 24) + (ch2 << 18) + (ch3 << 12) +
          (ch4 << 6) + ch5;
      ch6 = Agetchar() & 0x3F;
      return ((ch & 0x01) << 30) + (ch2 << 24) + (ch3 << 18) +
        (ch4 << 12) + (ch5 << 6) + ch6;
   case 3: /* HZ */
     ch = Agetchar();
     if (ch == EOF) return ch;
     if (hzmode) {
       ch = (ch << 8) + Agetchar();
       if (ch == ('}' << 8) + '~') {
         hzmode = 0;
         return getinchr();
         }
       return ch;
       }
     else if (ch == '~') {
       ch = Agetchar();
       if (ch == '{') {
          hzmode = 1;
          return getinchr();
          }
      else if (ch == '~') {
        return ch;
        }
      else {
        return getinchr();
        }
      }
     else return ch;
   case 4: /* Shift-JIS */
     ch = Agetchar();
     if ((ch >= 0x80 && ch <= 0x9F) ||
         (ch >= 0xE0 && ch <= 0xEF)) {
       ch = (ch << 8) + Agetchar();
       }
     return ch;
   default:
     return 0x80;
    }
  }

/****************************************************************************

  main

  The main program, of course.
  Reads characters 1 by 1 from stdin, and makes lines out of them using
  addchar. Handles line breaking, (which accounts for most of the
  complexity in this function).

****************************************************************************/

int main(argc,argv)
int argc;
char *argv[];
{
  inchr c,c2;
  int i;
  int last_was_eol_flag;
/*---------------------------------------------------------------------------
  wordbreakmode:
    -1: /^$/ and blanks are to be absorbed (when line break was forced
      by a blank or character larger than outlinelenlimit)
    0: /^ *$/ and blanks are not to be absorbed
    1: /[^ ]$/ no word break yet
    2: /[^ ]  *$/
    3: /[^ ]$/ had a word break
---------------------------------------------------------------------------*/
  int wordbreakmode;
  int char_not_added;

  Myargc = argc;
  Myargv = argv;
  getparams();
  readcontrolfiles();
  readfont();
  linealloc();

  wordbreakmode = 0;
  last_was_eol_flag = 0;

#ifdef TLF_FONTS
  toiletfont = 0;
#endif

  while ((c = getinchr())!=EOF) {

    if (c=='\n'&&paragraphflag&&!last_was_eol_flag) {
      ungetinchr(c2 = getinchr());
      c = ((isascii(c2)&&isspace(c2))?'\n':' ');
      }
    last_was_eol_flag = (isascii(c)&&isspace(c)&&c!='\t'&&c!=' ');

    if (deutschflag) {
      if (c>='[' && c<=']') {
        c = deutsch[c-'['];
        }
      else if (c >='{' && c <= '~') {
        c = deutsch[c-'{'+3];
        }
      }

    c = handlemapping(c);

    if (isascii(c)&&isspace(c)) {
      c = (c=='\t'||c==' ') ? ' ' : '\n';
      }

    if ((c>'\0' && c<' ' && c!='\n') || c==127) continue;

/*
  Note: The following code is complex and thoroughly tested.
  Be careful when modifying!
*/

    do {
      char_not_added = 0;

      if (wordbreakmode== -1) {
        if (c==' ') {
          break;
          }
        else if (c=='\n') {
          wordbreakmode = 0;
          break;
          }
        wordbreakmode = 0;
        }

      if (c=='\n') {
        printline();
        wordbreakmode = 0;
        }

      else if (addchar(c)) {
        if (c!=' ') {
          wordbreakmode = (wordbreakmode>=2)?3:1;
          }
        else {
          wordbreakmode = (wordbreakmode>0)?2:0;
          }
        }

      else if (outlinelen==0) {
        for (i=0;i<charheight;i++) {
          if (right2left && outputwidth>1) {
            putstring(currchar[i]+STRLEN(currchar[i])-outlinelenlimit);
            }
          else {
            putstring(currchar[i]);
            }
          }
        wordbreakmode = -1;
        }

      else if (c==' ') {
        if (wordbreakmode==2) {
          splitline();
          }
        else {
          printline();
          }
        wordbreakmode = -1;
        }

      else {
        if (wordbreakmode>=2) {
          splitline();
          }
        else {
          printline();
          }
        wordbreakmode = (wordbreakmode==3)?1:0;
        char_not_added = 1;
        }

      } while (char_not_added);
    }

  if (outlinelen!=0) {
    printline();
    }
  return 0;
}
