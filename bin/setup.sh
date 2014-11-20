#!/usr/bin/env bash

CALLEDPATH=`dirname $0`

# Convert to an absolute path if necessary
case "$CALLEDPATH" in
  .*)
    CALLEDPATH="$PWD/$CALLEDPATH"
    ;;
esac

if [ ! -f "$CALLEDPATH/setup.conf" ]; then
  echo
  echo "Missing configuration file. Please copy $CALLEDPATH/setup.conf.template to $CALLEDPATH/setup.conf and edit it."
  exit 1
fi

source "$CALLEDPATH/setup.conf"

cp $CIVIROOT/xml/schema/Schema.xml $CIVIROOT/xml/schema/Schema.xml.backup

# append extension schema to core schema
sed -i 's#</database>##' "$CIVIROOT/xml/schema/Schema.xml"
grep "<xi:include" "$EXTENSIONROOT/xml/schema/Schema.xml" >> "$CIVIROOT/xml/schema/Schema.xml"
echo "</database>" >> "$CIVIROOT/xml/schema/Schema.xml"

if [ ! -e "$CIVIROOT/xml/schema/$CLASSDIR" ] ; then
  ln -s $EXTENSIONROOT/xml/schema/$CLASSDIR $CIVIROOT/xml/schema/$CLASSDIR
fi
cd $CIVIROOT/xml
php GenCode.php

cp -rf $CIVIROOT/CRM/$CLASSDIR/DAO/ $EXTENSIONROOT/CRM/$CLASSDIR/
mv $CIVIROOT/xml/schema/Schema.xml.backup $CIVIROOT/xml/schema/Schema.xml

unlink $CIVIROOT/xml/schema/$CLASSDIR
rm -rf $CIVIROOT/CRM/$CLASSDIR
