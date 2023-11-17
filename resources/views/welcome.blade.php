<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <link rel="icon" type="image/svg+xml" href="/logo.svg" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Техподдержка v2</title>
  @vite('resources/css/app.css')
  <script src="//api.bitrix24.com/api/v1/"></script>
</head>

<body>
  <script>
    const user = @json($user ?? 'null');
    const ticket_id = @json($ticket_id ?? '0');

    window.user = user
    window.ticket_id = ticket_id
  </script>

  <div id="app"></div>
  @vite('resources/js/main.js')
</body>

</html>