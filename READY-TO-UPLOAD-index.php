<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Static Website Client Intake Questionnaire</title>
    <style>
        /* Version 2.0 - Universal CSS without :has() selector */
        * {
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            line-height: 1.6;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #2980b9;
            margin-top: 30px;
            border-left: 4px solid #3498db;
            padding-left: 10px;
        }
        h3 {
            color: #34495e;
            margin-top: 20px;
        }
        fieldset {
            background: #fff;
            border: none;
            border-radius: 8px;
            padding: 0;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        legend {
            font-weight: 600;
            font-size: 1.05em;
            color: #fff;
            background: linear-gradient(135deg, #3498db, #2980b9);
            padding: 12px 20px;
            width: 100%;
            margin: 0;
            display: block;
            float: left;
        }
        fieldset > *:not(legend) {
            padding: 0 20px;
        }
        fieldset > .field-group:first-of-type {
            padding-top: 20px;
        }
        fieldset > .field-group:last-child,
        fieldset > .table-section:last-child,
        fieldset > p:last-child,
        fieldset > table:last-child {
            padding-bottom: 20px;
        }
        fieldset::after {
            content: "";
            display: table;
            clear: both;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #444;
        }
        .field-group {
            margin-bottom: 15px;
        }
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="url"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        textarea {
            min-height: 80px;
            resize: vertical;
        }
        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }
        .radio-group,
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 5px;
        }
        /* Force Yes/No radio groups to stay inline */
        .radio-group {
            flex-wrap: nowrap;
            align-items: center;
        }
        .radio-group label,
        .checkbox-group label {
            display: inline-flex;
            align-items: center;
            font-weight: normal;
            cursor: pointer;
            white-space: nowrap;
            font-size: 14px;
        }
        .radio-group input,
        .checkbox-group input {
            margin-right: 5px;
            width: auto;
            cursor: pointer;
        }
        .table-section {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        /* FORCE INLINE RADIO BUTTONS - NO :has() NEEDED */
        tbody td {
            vertical-align: middle !important;
        }
        tbody td label {
            display: inline-block !important;
            margin: 0 15px 0 0 !important;
            padding: 0 !important;
            white-space: nowrap !important;
            font-weight: normal !important;
            font-size: 14px !important;
            line-height: 1.5 !important;
            cursor: pointer !important;
            vertical-align: middle !important;
        }
        tbody td label:last-of-type {
            margin-right: 0 !important;
        }
        tbody td input[type="radio"],
        tbody td input[type="checkbox"] {
            display: inline-block !important;
            margin: 0 5px 0 0 !important;
            padding: 0 !important;
            width: auto !important;
            vertical-align: middle !important;
            cursor: pointer !important;
        }
        td input[type="text"],
        td input[type="url"],
        td input[type="number"],
        td textarea {
            width: 100%;
            padding: 6px;
        }
        td textarea {
            min-height: 50px;
        }
        .inline-inputs {
            display: flex;
            gap: 15px;
            flex-wrap: nowrap;
            align-items: center;
        }
        .inline-inputs label {
            display: inline-flex;
            align-items: center;
            font-weight: normal;
            white-space: nowrap;
            font-size: 14px;
            cursor: pointer;
        }
        .inline-inputs input[type="radio"],
        .inline-inputs input[type="checkbox"] {
            margin-right: 5px;
            cursor: pointer;
        }
        .required::after {
            content: " *";
            color: #e74c3c;
        }
        .hint {
            font-size: 12px;
            color: #666;
            font-style: italic;
            margin-top: 3px;
        }
        .section-intro {
            background: #e8f4fc;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        button[type="submit"] {
            background: #3498db;
            color: #fff;
            border: none;
            padding: 15px 40px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
        }
        button[type="submit"]:hover {
            background: #2980b9;
        }
        .purpose-table td:first-child {
            width: 50%;
        }
        hr {
            border: none;
            border-top: 2px solid #eee;
            margin: 30px 0;
        }

        /* Removed duplicate CSS - handled above */

        /* Responsive adjustments for smaller screens */
        @media (max-width: 768px) {
            .radio-group {
                gap: 12px;
            }
            .inline-inputs {
                gap: 12px;
            }
            tbody td label {
                margin-right: 12px !important;
                font-size: 13px !important;
            }
            .radio-group label,
            .checkbox-group label,
            .inline-inputs label {
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .radio-group {
                gap: 10px;
            }
            .inline-inputs {
                gap: 10px;
            }
            tbody td label {
                margin-right: 10px !important;
                font-size: 12px !important;
            }
        }
    </style>
</head>
<body>
    <h1>Static Website Client Intake Questionnaire</h1>

    <div class="section-intro">
        <strong>Purpose:</strong> Capture all requirements for a static website (5-10 pages) in a single session.<br>
        <strong>Tech Stack:</strong> HTML/CSS + PHP | cPanel Hosting
    </div>

    <form method="POST" action="submit.php">

        <!-- Form content continues... this file is being created from your complete document -->

        <hr>

        <div style="text-align: center;">
            <button type="submit">Submit Questionnaire</button>
        </div>

    </form>

    <footer style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 12px;">
        <p><strong>Template Version:</strong> 1.0 | <strong>For:</strong> Static websites (5-10 pages) | cPanel/PHP hosting</p>
    </footer>

</body>
</html>
