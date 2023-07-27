<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <link rel="icon" type="image/svg+xml" href="/logo.svg" />
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Техподдержка</title>
  @vite('resources/css/app.css')
  <script src="//api.bitrix24.com/api/v1/"></script>
</head>

<body>
  <script>
    const user = @json($user);
    window.user = user
    // console.log(window.user)
    // console.log(localStorage.getItem('support_access'))
    const token = @json($token);
    const ticket_id = @json($ticket_id);
    window.ticket_id = ticket_id
    // const token = '1|0kuajODW8Wtth1blM49QhcWmneo4wK5gtImtn08Q'
    // console.log('token - '+ token)
    if (token != '') {
      localStorage.removeItem('support_access')
      localStorage.setItem('support_access', token)
    }

    // console.log(localStorage.getItem('support_access'))
  </script>

  <div id="app" class="w-screen"></div>
  @vite('resources/js/main.js')
</body>

</html>