@extends('admin.layout.layout')

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('img/logo.png') }}">
    @section('title', 'Reports')

    <style>
        .content-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 10px;
            overflow: hidden;
            background: rgb(242, 242, 242);
            border-top-left-radius: 30px;
            margin: 0;
            box-shadow: none;
            position: relative;
            min-height: 0;
        }
    </style>

</head>

<body>
    @section('content')

        <div class="content-area">
            <h1>Hello World</h1>
        </div>

    @endsection
</body>

</html>
