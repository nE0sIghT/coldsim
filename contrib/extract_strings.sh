#!/bin/sh

xgettext --sort-output \
--keyword=translatable \
--copyright-holder=nE0sIghT \
--package-name=ColdSim \
--package-version=1.0 \
--msgid-bugs-address="http://bugs.coldzone.ru" \
-o ./strings/coldsim.po ../gui/coldsim.glade