<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <link rel="icon" type="image/svg+xml" href="/logo.svg" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Техподдержка v2</title>
  @vite('resources/css/app.css')
  <script src="//api.bitrix24.com/api/v1/"></script>
</head>

<body>
  <script>
    const user = @json($user);
    const token = @json($token);
    const ticket_id = @json($ticket_id);

    window.user = user
    window.ticket_id = ticket_id

    if (token != '') {
      localStorage.removeItem('support_access')
      localStorage.setItem('support_access', token)
    }
  </script>

  <div id="app"></div>
  @vite('resources/js/main.js')
</body>

</html>