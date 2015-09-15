#include <stdio.h>
#include <string.h>
#include <ctype.h>
#ifdef __STDC__
#include <stdlib.h>
#endif

#define DATE "20 Feb 1996"
#define VERSION "2.2"

/*
   chkfont
   By Glenn Chappell <ggc@uiuc.edu>

   This program checks figlet 2.0/2.1 font files for format errors.
   It also looks for signs of common problems and gives warnings.
   chkfont does not modify font files.

   Usage: chkfont fontfile ...

   Note: This is very much a spare-time project. It's probably
   full o' bugs ....
*/

/* #define CHECKBLANKS */
#define FONTFILESUFFIX ".flf"
#define FONTFILEMAGICNUMBER "flf2"
char posshardblanks[9] = { '!', '@', '#', '$', '%', '&', '*', 0x7f, 0 };

char *myname,*fontfilename;
FILE *fontfile;
char hardblank;
int charheight,upheight,maxlen=0,old_layout;
int spectagcnt;
char *fileline;
int maxlinelength=0,currline;
int ec,wc;

int incon_endmarkwarn,endmark_countwarn,nonincrwarn;
int bigcodetagwarn,deutschcodetagwarn,asciicodetagwarn;
int codetagcnt;
int gone;

void weregone(really)
int really;
{
if (!really && 2*ec+wc<=40) {
  return;
  }
if (ec+wc>0) printf("*******************************************************************************\n");
if (!really) {
  printf("%s: Too many errors/warnings.\n",fontfilename);
  }
printf("%s: Errors: %d, Warnings: %d\n",fontfilename,ec,wc);
if (currline>1 && maxlen!=maxlinelength) {
  printf("%s: maxlen: %d, actual max line length: %d\n",
    fontfilename,maxlen,maxlinelength);
  if (codetagcnt>0 && spectagcnt==-1) {
    printf("%s: Code-tagged characters: %d\n",fontfilename,codetagcnt);
    }
  }
printf("-------------------------------------------------------------------------------\n");
gone=1;
}

char *my_alloc(size)
int size;
{
char *ptr;

ptr=(char *)malloc(size);
if (ptr==NULL) {
  fprintf(stderr,"%s: Out of memory\n",myname);
  exit(1);
  }
return(ptr);
}

int badsuffix(path,suffix)
char *path;
char *suffix;
{
  char ucsuffix[10];
  char *s;

  strcpy(ucsuffix,suffix);
  for (s = ucsuffix; *s; s++) {
    *s = toupper(*s);
    }

  if (strlen(path)<strlen(suffix)) return 1;
  s = path + strlen(path) - strlen(suffix);
  if (strcmp(s,suffix) == 0) return 0;
  if (strcmp(s,ucsuffix) == 0) return 0;
  return 1;
}

void usageerr()
{
fprintf(stderr,"chkfont by Glenn Chappell <ggc@uiuc.edu>\n");
fprintf(stderr,"Version: %s, date: %s\n",VERSION,DATE);
fprintf(stderr,"Checks figlet 2.0/2.1 font files for format errors.\n");
fprintf(stderr,"(Does not modify font files.)\n");
fprintf(stderr,"Usage: %s fontfile ...\n",myname);
exit(1);
}

void readchar()
{
int i,expected_width,k,len,newlen,diff,l;
char endmark,expected_endmark;
int leadblanks,minleadblanks,trailblanks,mintrailblanks;
char *ret;

expected_width = expected_endmark = 0;	/* prevent compiler warning */
for (i=0;i<charheight;i++) {
  ret = fgets(fileline,maxlen+1000,fontfile);
  if (ret == NULL) {
    printf("%s: ERROR (fatal)- Unexpected read error after line %d.\n",
      fontfilename,currline);
    ec++;
    weregone(1); if (gone) return;
    }
  if (feof(fontfile)) {
    printf("%s: ERROR (fatal)- Unexpected end of file after line %d.\n",
      fontfilename,currline);
    ec++;
    weregone(1); if (gone) return;
    }
  currline++;
  len=strlen(fileline)-1;
  if (len>maxlinelength) {
    maxlinelength=len;
    }
  if (len>maxlen) {
    printf("%s: ERROR- Line length > maxlen in line %d.\n",
      fontfilename,currline);
    ec++;
    weregone(0); if (gone) return;
    }
  k=len;
  endmark=k<0?'\0':(k==0||fileline[k]!='\n')?fileline[k]:fileline[k-1];
  for(;k>=0?(fileline[k]=='\n' || fileline[k]==endmark):0;k--) {
    fileline[k]='\0';
    }
  newlen=strlen(fileline);
  for (l=0;l<newlen ? fileline[l]==' ' : 0;l++) ;
  leadblanks = l;
  for (l=newlen-1;l>=0 ? fileline[l]==' ' : 0;l--) ;
  trailblanks = newlen-1-l;
  if (i==0) {
    expected_endmark = endmark;
    expected_width = newlen;
    minleadblanks = leadblanks;
    mintrailblanks = trailblanks;
    if (endmark==' ') {
      printf("%s: Warning- Blank endmark in line %d.\n",
        fontfilename,currline);
      wc++;
      weregone(0); if (gone) return;
      }
    }
  else {
    if (leadblanks<minleadblanks) minleadblanks = leadblanks;
    if (trailblanks<mintrailblanks) mintrailblanks = trailblanks;
    if (endmark!=expected_endmark && !incon_endmarkwarn) {
      printf("%s: Warning- Inconsistent endmark in line %d.\n",
        fontfilename,currline);
      printf("%s:          (Above warning will only be printed once.)\n",
        fontfilename);
      incon_endmarkwarn = 1;
      wc++;
      weregone(0); if (gone) return;
      }
    if (newlen!=expected_width) {
      printf("%s: ERROR- Inconsistent character width in line %d.\n",
        fontfilename,currline);
      ec++;
      weregone(0); if (gone) return;
      }
    }
  diff=len-newlen;
  if (diff>2) {
    printf("%s: ERROR- Too many endmarks in line %d.\n",
      fontfilename,currline);
    ec++;
    weregone(0); if (gone) return;
    }
  else if (charheight>1 && (diff!=(i==charheight-1)+1)) {
    if (!endmark_countwarn) {
      printf("%s: Warning- Endchar count convention violated in line %d.\n",
        fontfilename,currline);
      printf("%s:          (Above warning will only be printed once.)\n",
        fontfilename);
      endmark_countwarn = 1;
      wc++;
      weregone(0); if (gone) return;
      }
    }
  }
#ifdef CHECKBLANKS
if (minleadblanks+mintrailblanks>0 && old_layout>=0) {
  printf("%s: Warning- Leading/trailing blanks in char. ending at line %d.\n",
    fontfilename,currline);
  printf("%s:          (Above warning only given when old_layout > -1.)\n",
    fontfilename);
  wc++;
  weregone(0); if (gone) return;
  }
#endif /* #ifdef CHECKBLANKS */
}


void checkit()
{
int i,k,cmtcount,numsread,ffrighttoleft,have_layout,layout;
char magicnum[5],cha;
long oldord,theord;
int tmpcnt,len;

ec=0;wc=0;
incon_endmarkwarn=0; endmark_countwarn=0; nonincrwarn=0;
bigcodetagwarn=0; deutschcodetagwarn=0;
asciicodetagwarn=0;
codetagcnt=0;
gone=0;
if (!strcmp(fontfilename,"-")) {
  fontfilename="(stdin)";
  fontfile=stdin;
  }
else {
  fontfile=fopen(fontfilename,"r");
  if (fontfile == NULL) {
    fprintf(stderr,"%s: Could not open file '%s'\n",myname,fontfilename);
    exit(1);
    }
  }

if (fontfile!=stdin) {
  if (badsuffix(fontfilename,FONTFILESUFFIX)) {
    printf("%s: ERROR- Filename does not end with '%s'.\n",
      fontfilename,FONTFILESUFFIX);
    ec++;
    weregone(0); if (gone) return;
    }
  }
numsread=fscanf(fontfile,"%4s",magicnum);
if (numsread == EOF) {
  printf("%s: ERROR- can't read magic number.\n",fontfilename);
  ec++;
  weregone(0); if (gone) return;
  }
if (strcmp(magicnum,FONTFILEMAGICNUMBER)) {
  printf("%s: ERROR- Incorrect magic number.\n",fontfilename);
  ec++;
  weregone(0); if (gone) return;
  }
cha=getc(fontfile);
if (cha!='a') {
  printf("%s: Warning- Sub-version character is not 'a'.\n",fontfilename);
  wc++;
  weregone(0); if (gone) return;
  }
fileline=(char*)my_alloc(sizeof(char)*(1001));
if (fgets(fileline,1001,fontfile)==NULL) {
  fileline[0] = '\0';
  }
if (strlen(fileline)>0 ? fileline[strlen(fileline)-1]!='\n' : 0) {
  while(k=getc(fontfile),k!='\n'&&k!=EOF) ; /* Advance to end of line */
  }
numsread=sscanf(fileline,"%c %d %d %d %d %d %d %d %d",
  &hardblank,&charheight,&upheight,&maxlen,&old_layout,&cmtcount,
  &ffrighttoleft,&layout,&spectagcnt);
free(fileline);
fileline = NULL;
if (numsread<7) {
  ffrighttoleft=0;
  }
if (numsread<9) {
  spectagcnt=-1;
  }
have_layout = (numsread>=8);
if (6>numsread) {
  printf("%s: ERROR (fatal)- First line improperly formatted.\n",fontfilename);
  ec++;
  weregone(1); if (gone) return;
  }
if (!strchr(posshardblanks,hardblank)) {
  printf("%s: Warning- Unusual hardblank.\n",fontfilename);
  wc++;
  weregone(0); if (gone) return;
  }
if (charheight<1) {
  printf("%s: ERROR (fatal)- charheight not positive.\n",fontfilename);
  ec++;
  weregone(1); if (gone) return;
  }
if (upheight>charheight || upheight<1) {
  printf("%s: ERROR- up_height out of bounds.\n",fontfilename);
  ec++;
  weregone(0); if (gone) return;
  }
if (maxlen<1) {
  printf("%s: ERROR (fatal)- maxlen not positive.\n",fontfilename);
  ec++;
  weregone(1); if (gone) return;
  }
if (old_layout<-1) {
  printf("%s: ERROR- old_layout < -1.\n",fontfilename);
  ec++;
  weregone(0); if (gone) return;
  }
if (old_layout>63) {
  printf("%s: ERROR- old_layout > 63.\n",fontfilename);
  ec++;
  weregone(0); if (gone) return;
  }
if (have_layout && layout<0) {
  printf("%s: ERROR- layout < 0.\n", fontfilename);
  ec++;
  weregone(0); if (gone) return;
  }
if (have_layout &&layout>32767) {
  printf("%s: ERROR- layout > 32767.\n", fontfilename);
  ec++;
  weregone(0); if (gone) return;
  }
if (have_layout && old_layout == -1 && (layout & 192)) {
  printf("%s: ERROR- layout %d is inconsistent with old_layout -1.\n",
    fontfilename,layout);
  ec++;
  weregone(0); if (gone) return;
  }
if (have_layout && old_layout == 0 && (layout & 192) != 64 &&
                                   (layout & 255) != 128) {
  printf("%s: ERROR- layout %d is inconsistent with old_layout 0.\n",
    fontfilename,layout);
  ec++;
  weregone(0); if (gone) return;
  }
if (have_layout && old_layout > 0 &&
      (!(layout & 128) || old_layout != (layout & 63))) {
  printf("%s: ERROR- layout %d is inconsistent with old_layout %d.\n",
    fontfilename,layout,old_layout);
  ec++;
  weregone(0); if (gone) return;
  }
if (cmtcount<0) {
  printf("%s: ERROR- cmt_count is negative.\n",fontfilename);
  ec++;
  weregone(0); if (gone) return;
  }
if (ffrighttoleft<0 || ffrighttoleft>1) {
  printf("%s: ERROR- rtol out of bounds.\n",fontfilename);
  ec++;
  weregone(0); if (gone) return;
  }

for (i=1;i<=cmtcount;i++) {
  while(k=getc(fontfile),k!='\n'&&k!=EOF) ; /* Advance to end of line */
  }

maxlinelength = 0;
currline=cmtcount+1;
fileline=(char*)my_alloc(sizeof(char)*(maxlen+1001));
for (i=0;i<102;i++) {
  readchar();
  if (gone) return;
  }

oldord=0;
while(fgets(fileline,maxlen+1000,fontfile)!=NULL) {
  currline++;
  len=strlen(fileline)-1;
  if (len-100>maxlinelength) {
    maxlinelength=len-100;
    }
  if (len>maxlen+100) {
    printf("%s: ERROR- Code tag line way too long in line %d.\n",
      fontfilename,currline);
    ec++;
    weregone(0); if (gone) return;
    }
  tmpcnt=sscanf(fileline,"%li",&theord);
  if (tmpcnt<1) {
    printf("%s: Warning- Extra chars after font in line %d.\n",
      fontfilename,currline);
    wc++;
    weregone(0); if (gone) return;
    break;
    }
  codetagcnt++;
  if (theord>65535 && !bigcodetagwarn) {
    printf("%s: Warning- Code tag > 65535 in line %d.\n",
      fontfilename,currline);
    printf("%s:          (Above warning will only be printed once.)\n",
      fontfilename);
    bigcodetagwarn = 1;
    wc++;
    weregone(0); if (gone) return;
    }
  if (theord==-1) {
    printf("%s: ERROR- Code tag -1 (unusable) in line %d.\n",
      fontfilename,currline);
    ec++;
    weregone(0); if (gone) return;
    break;
    }
  if (theord>=-255 && theord<=-249 &&!deutschcodetagwarn) {
    printf("%s: Warning- Code tag in old Deutsch area in line %d.\n",
      fontfilename,currline);
    printf("%s:          (Above warning will only be printed once.)\n",
      fontfilename);
    deutschcodetagwarn = 1;
    wc++;
    weregone(0); if (gone) return;
    }
  if (theord<127 && theord>31 && !asciicodetagwarn) {
    printf("%s: Warning- Code tag in ASCII range in line %d.\n",
      fontfilename,currline);
    printf("%s:          (Above warning will only be printed once.)\n",
      fontfilename);
    asciicodetagwarn = 1;
    wc++;
    weregone(0); if (gone) return;
    }
  else if (theord<=oldord && theord>=0 && oldord>=0 && !nonincrwarn) {
    printf("%s: Warning- Non-increasing code tag in line %d.\n",
      fontfilename,currline);
    printf("%s:          (Above warning will only be printed once.)\n",
      fontfilename);
    nonincrwarn = 1;
    wc++;
    weregone(0); if (gone) return;
    }
  oldord=theord;
  readchar();
  if (gone) return;
  }

if (spectagcnt!=-1 && spectagcnt!=codetagcnt) {
  printf("%s: ERROR- Inconsistent Codetag_Cnt value %d\n",
    fontfilename, spectagcnt);
  ec++;
  weregone(0); if (gone) return;
  }

if (fontfile!=stdin) fclose(fontfile);

weregone(1); if (gone) return;
}


int main(argc,argv)
int argc;
char *argv[];
{
int arg;

if ((myname=strrchr(argv[0],'/'))!=NULL) {
  myname++;
  }
else {
  myname = argv[0];
  }
if (argc<2) {
  usageerr();
  }
for (arg=1;arg<argc;arg++) {
  fontfilename=argv[arg];
  fileline=NULL;
  checkit();
  if (fileline!=NULL) free(fileline);
  }
return 0;
}
