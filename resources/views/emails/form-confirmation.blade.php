<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 32px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        h1 { font-size: 1.5rem; margin-top: 0; color: #1a202c; }
        h2 { font-size: 1.25rem; color: #2d3748; margin-top: 1.5rem; }
        h3 { font-size: 1.1rem; color: #4a5568; }
        p { margin: 1rem 0; }
        a { color: #3182ce; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        {!! Str::markdown($emailBody) !!}
    </div>
</body>
</html>
