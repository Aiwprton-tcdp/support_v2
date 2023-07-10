<!DOCTYPE html>
<html lang="en">
@if($result['rest_only'] === false)
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <script src="//api.bitrix24.com/api/v1/"></script>
        @if($result['install'] == true)
            <script>
                BX24.init(function () {
                    console.log(BX24.installFinish());
                });
            </script>
        @endif
    </head>
    <body>
    @if($result['install'] == true)
        <script>
            alert("Успешно");
        </script>
        Установка была завершена
    @else
        Ошибка установки
    @endif
    </body>
@endif
</html>
