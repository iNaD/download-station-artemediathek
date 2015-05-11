:: Delete old data
del artemediathek.host

:: get recent version of the provider base class
copy /Y ..\provider-boilerplate\src\provider.php provider.php

:: create the .tar.gz
7z a -ttar -so artemediathek INFO artemediathek.php provider.php | 7z a -si -tgzip artemediathek.host

del provider.php