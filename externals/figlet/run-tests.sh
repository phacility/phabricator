#!/bin/sh

LC_ALL=POSIX
export LC_ALL

TESTDIR=tests
OUTPUT=.test-output.txt
LOGFILE=tests.log
CMD="./figlet"
FONTDIR="$1"

run_test() {
	test_dsc=$1
	test_cmd=$2

	total=`expr $total + 1`
	test_num=`printf %03d $total`

	echo >> $LOGFILE
	printf "Run test $test_num: ${test_dsc}... " | tee -a $LOGFILE
	echo >> $LOGFILE
	echo "Command: $test_cmd" >> $LOGFILE
	eval "$test_cmd" > "$OUTPUT" 2>> $LOGFILE
	cmp "$OUTPUT" "tests/res${test_num}.txt" >> $LOGFILE 2>&1
	if [ $? -eq 0 ]; then
		echo "pass" | tee -a $LOGFILE
	else
		echo "**fail**" | tee -a $LOGFILE
		result=1
		fail=`expr $fail + 1`
	fi
}

result=0
fail=0
$CMD -v > $LOGFILE

file="$TESTDIR/input.txt"
cmd="cat $file|$CMD"

printf "Default font dir: "; $CMD -I2
if [ -n "$FONTDIR" ]; then
	FIGLET_FONTDIR="$FONTDIR"
	export FIGLET_FONTDIR
fi
printf "Current font dir: "; $CMD -I2
printf "Default font: "; $CMD -I3
$CMD -f small "Test results" | tee -a $LOGFILE

total=0

run_test "showfigfonts output" "./showfigfonts"
run_test "text rendering in all fonts" \
  "for i in fonts/*.flf; do $cmd -f \$i; done"
run_test "long text rendering" "cat tests/longtext.txt|$CMD"
run_test "left-to-right text" "$cmd -L"
run_test "right-to-left text" "$cmd -R"
run_test "flush-left justification" "$cmd -l"
run_test "flush-right justification" "$cmd -r"
run_test "center justification" "$cmd -c"
run_test "kerning mode" "$cmd -k"
run_test "full width mode" "$cmd -W"
run_test "overlap mode" "$cmd -o"
run_test "tlf2 font rendering" "$cmd -f tests/emboss"
run_test "kerning flush-left right-to-left mode" "$cmd -klR"
run_test "kerning centered right-to-left mode (slant)" "$cmd -kcR -f slant"
run_test "full-width flush-right right-to-left mode" "$cmd -WrR"
run_test "overlap flush-right mode (big)" "$cmd -or -f big"
run_test "tlf2 kerning flush-right mode" "$cmd -kr -f tests/emboss"
run_test "tlf2 overlap centered mode" "$cmd -oc -f tests/emboss"
run_test "tlf2 full-width flush-left right-to-left mode" \
  "$cmd -WRl -f tests/emboss"
run_test "specify font directory" \
  "X=.t;mkdir \$X;cp fonts/script.flf \$X/foo.flf;$cmd -d\$X -ffoo;rm -Rf \$X"
run_test "paragraph mode long line output" "$cmd -p -w250"
run_test "short line output" "$cmd -w5"
run_test "kerning paragraph centered mode (small)" "$cmd -kpc -fsmall"
run_test "list of control files" "ls fonts/*flc"
run_test "uskata control file" "printf 'ABCDE'|$CMD -fbanner -Cuskata"
run_test "jis0201 control file" "printf '\261\262\263\264\265'|$CMD -fbanner -Cjis0201"
run_test "right-to-left smushing with JavE font" "$cmd -f tests/flowerpower -R"

rm -f "$OUTPUT"

echo
if [ $result -ne 0 ]; then
	echo " $fail tests failed. See $LOGFILE for result details"
else
	echo " All tests passed."
fi

exit $result
