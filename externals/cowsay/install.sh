#!/bin/sh

##
## install.sh
##
## Installation script for cowsay.
##
## $Id: install.sh,v 1.5 1999/11/01 20:19:21 tony Exp $
##
## This file is part of cowsay.  (c) 1999 Tony Monroe.
##

rcs_id='$Id: install.sh,v 1.5 1999/11/01 20:19:21 tony Exp $'

filelist='cows'

cat <<DOG
===================
cowsay Installation
===================

Searching for useful perl executables...
DOG

backdoor=$1

pathdirs=`echo $PATH | tr : " "`
for p in $pathdirs; do
	set $p/perl $p/perl5*
	while [ ! -z "$1" ]; do
		if [ -x "$1" ]; then
			echo Found perl in $1
			perls="$perls $1"
		fi
		shift
	done
done
for perl in $perls; do
	if $perl -MText::Wrap -e0 >/dev/null 2>&1; then
		echo Found a good perl in $perl
		goodperls="$goodperls $perl"
	fi
done
echo The following perl executables will run cowsay:
echo $goodperls
echo I recommend the latest stable perl you can find.
set $goodperls
if [ -z "$1" ]; then
	echo Ack!  You do not have Perl 5 installed correctly!
	echo Get thee to CPAN!
	exit 1
fi
usethisperl=$1
echo I will be using $1 because I know it will work.

echo Now I need an installation prefix.  I will use /usr/local unless
printf "you give me a better idea here: "
if [ -n "$backdoor" ]; then
	prefix=$backdoor
	printf "%s (specified on command line)\n" $prefix
else
	read prefix
fi

PREFIX=${prefix:-/usr/local}

echo Okay, time to install this puppy.

echo s,%BANGPERL%,!$usethisperl,\; > install.pl
echo s,%PREFIX%,$PREFIX,\; >> install.pl
set -x
mkdir -p $PREFIX/bin || (mkdir $PREFIX; mkdir $PREFIX/bin)
$usethisperl -p install.pl cowsay > $PREFIX/bin/cowsay
chmod a+x $PREFIX/bin/cowsay
ln -s cowsay $PREFIX/bin/cowthink
mkdir -p $PREFIX/man/man1 || ($mkdir $PREFIX; mkdir $PREFIX/man; mkdir $PREFIX/man/man1)
$usethisperl -p install.pl cowsay.1 > $PREFIX/man/man1/cowsay.1
chmod a+r $PREFIX/man/man1/cowsay.1
ln -s cowsay.1 $PREFIX/man/man1/cowthink.1
mkdir -p $PREFIX/share/cows || (mkdir $PREFIX; mkdir $PREFIX/share; mkdir $PREFIX/share/cows)
tar -cf - $filelist | (cd $PREFIX/share && tar -xvf -)
set +x

echo Okay, let us see if the install actually worked.

if [ ! -f $PREFIX/share/cows/default.cow ]; then
    echo The default cow file did not make it across!
    echo Ooops, it failed...sorry!
    exit 1
fi

echo Installation complete!  Enjoy the cows!
