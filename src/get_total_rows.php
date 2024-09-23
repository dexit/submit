<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    $csvFile = $_FILES['csvFile']['tmp_name'];

    $file = fopen($csvFile, 'r');
    if (!$file) {
        echo 0;
        exit;
    }

    fgetcsv($file);

    $rowCount = 0;
    while (fgetcsv($file) !== false) {
        $rowCount++;
    }

    fclose($file);
    echo $rowCount;
} else {
    echo 0;
}

?>