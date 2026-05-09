<?php
$samples = [
    ["Robert","Branch","Haines city","FL","33844","4074686162","rbranch@pacbell.net","65.215.76.5"],
    ["Janny","Gaines","Augusta","GA","30904","7063065591","jannygaines@yahoo.com","152.85.91.83"],
    ["deborah","yankosky","milford","in","46542","5746584567","dyankosky@hotmail.com","69.253.17.250"]
];

$file = fopen('1M-customers.csv', 'w');
for ($i = 0; $i < 1000000; $i++) {
    $row = $samples[array_rand($samples)];
    // Randomly invalidate 1% of data for testing
    if ($i % 100 == 0) { $row[6] = "invalid_email"; }
    fputcsv($file, $row);
}
fclose($file);
echo "1 Million rows generated.\n";