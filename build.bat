:: Delete old data
del artemediathek.host
:: create the .tar.gz
7z a -ttar -so artemediathek INFO artemediathek.php | 7z a -si -tgzip artemediathek.host
