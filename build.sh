#!/bin/sh

unlink artemediathek.host

BoilerPlateDir=../download-station-boilerplate

if [ ! -d "$BoilerPlateDir" ]; then
	echo "We need iNaD's Boilerplate ..."
	git clone https://github.com/iNaD/download-station-boilerplate.git "$BoilerPlateDir"
fi

cp -f "$BoilerPlateDir/src/provider.php" ./provider.php

tar cvzf artemediathek.host INFO artemediathek.php provider.php

unlink provider.php

echo "We are done !"