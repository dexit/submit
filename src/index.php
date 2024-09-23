<!DOCTYPE html>
<html>
<head>
    <title>Email Validator</title>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script type="text/javascript" charset="utf8" src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <style>
        #progressBar {
            width: 0%;
            height: 20px;
            background-color: #4CAF50;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<h1>Email Validator</h1>

<form id="csvForm" method="post" enctype="multipart/form-data">
    <input type="file" name="csvFile" accept=".csv">
    <select name="emailColumnName">
        <!-- Email column options will be populated dynamically -->
    </select>
    <button type="submit">Validate</button>
</form>

<form id="emailForm" method="post">
    <input type="email" name="email" placeholder="Enter email address">
    <button type="submit">Validate</button>
</form>

<div id="progressBar"></div>
<div id="progressText"></div>

<table id="resultsTable">
    <thead>
        <tr>
            <th>Row</th>
            <th>Email</th>
            <th>Format</th>
            <th>TLD</th>
            <th>Domain</th>
            <th>MX Record</th>
            <th>SMTP</th>
        </tr>
    </thead>
    <tbody>
    </tbody>
</table>

<script>
$(document).ready(function() {
    var totalRows = 0;
    var currentRow = 0;

    function updateProgress(rowNumber) {
        $('#progressBar').css('width', (rowNumber / totalRows) * 100 + '%');
        $('#progressText').text('Processing row ' + rowNumber + ' of ' + totalRows);
    }

    $('#csvForm').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);

        $.ajax({
            url: 'get_total_rows.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                totalRows = parseInt(response);
                processCSV(formData);
            }
        });
    });

    function processCSV(formData) {
        $.ajax({
            url: 'email_validator.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = evt.loaded / evt.total;
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                var response = JSON.parse(response);
                if (response.status === 'success') {
                    var table = $('#resultsTable').DataTable();
                    table.clear().rows.add(response.data).draw();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    }

    $('#emailForm').on('submit', function(e) {
        e.preventDefault();

        var email = $('input[name="email"]').val();

        $.ajax({
            url: 'email_validator.php',
            type: 'POST',
            data: { email: email },
            success: function(response) {
                var response = JSON.parse(response);
                if (response.status === 'success') {
                    var table = $('#resultsTable').DataTable();
                    table.clear().rows.add([response.data]).draw(); // Wrap in an array for DataTables
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Error: ' + error);
            }
        });
    });

    $('input[name="csvFile"]').on('change', function() {
        var file = this.files[0];
        var reader = new FileReader();

        reader.onload = function(e) {
            var csvData = e.target.result;
            var rows = csvData.split('\n');
            var header = rows[0].split(',');

            var emailColumnOptions = '';
            for (var i = 0; i < header.length; i++) {
                emailColumnOptions += '<option value="' + header[i] + '">' + header[i] + '</option>';
            }

            $('select[name="emailColumnName"]').html(emailColumnOptions);
        };

        reader.readAsText(file);
    });
});
</script>

</body>
</html>