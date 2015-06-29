#!/bin/bash
# dokuwiki to markdown
# specified for Fontane Notizbuch-Projekt
# see fontane-notizbücher.de

# set up your wiki
WIKI=""
USER=""
PWD=""
CRED="u=$USER&p=$PWD"

# clean up
echo "clean up the working directories"
rm -rf input/
rm -rf output/
mkdir input
mkdir output

# and go ahead
echo "loading files from dokuWiki"
cd input
wget "$WIKI?id=fontane:startseite&do=export_raw&$CRED" &&
wget "$WIKI?id=fontane:impressum&do=export_raw&$CRED" &&
wget "$WIKI?id=fontane:institutionen&do=export_raw&$CRED" &&
wget "$WIKI?id=fontane:editionsteam&do=export_raw&$CRED" &&
wget "$WIKI?id=fontane:ueber_das_projekt&do=export_raw&$CRED" &&
wget "$WIKI?id=fontane:einfuehrung&do=export_raw&$CRED" &&

# ^^^ add more wgets as you like ^^^

#preprocessing
echo "rename and remove unsupported syntax"
rename "s/doku.php\?id=fontane:(.*)\&do\=export_raw\&u\=fontane-test\&p=VYZg18ITNFB59y3mEz8bCQ/\1.txt/" doku.php*
grep -l "\\" | xargs sed -i "s/\\\\//g"
cd ..

#convert
echo "convert..."
php convert.php input/ output/

echo "done. view your markdown files in output/"
