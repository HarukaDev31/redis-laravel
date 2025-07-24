<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <meta http-equiv="Content-Type" content="charset=utf-8" />
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; font-src 'self' https://fonts.gstatic.com; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';">
    <link href="https://fonts.cdnfonts.com/css/lucida-handwriting-std" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>
    <img src="data:image/png;base64,{{ $fondoImg }}" alt="Fondo">
    <!-- Tu contenido aquí -->
    <div class="container">
        <p class="text-orange font-medium weight-bold text-center " style="margin-bottom: 0.3em;margin-left:5em">PRO BUSINESS</p>
        <div style="margin: 0 1em;">
            <p class="font-xlarge weight-bold letter-spacing-medium"> CONSTANCIA </p>
            <p class="font-medium weight-normal letter-spacing-small" style="margin: 0.1em 0;"> DE RECONOCIMIENTO </p>
            <p class="font-small weight-medium" style="margin-bottom: 0.1em;">Otorgado a </p>
            <p class="family-lucida font-xsmall" style="margin-left: 0.4em;margin-bottom: 0.6em;">
                <span class="backgroud-gray ">{{$nombre}}</span>
            </p>
        </div>
        <p class="font-small weight-light">Por haber participado exitosamente en el <span class="weight-medium">CURSO VIRTUAL DE IMPORTACIÓN,</span></p>
        <p class="font-small weight-light">equivalente a 12 horas académicas.</p>
        <p class="font-small weight-light" style="margin-top: 0.4em;">Dictado por Danitza Vega, Melisa Valle y Miguel Villegas, en la modalidad online. </p>
        <p class="font-small text-right" style="margin-top: 2.5em;margin-left:26em">Lima, {{$fecha}} </p>
    </div>
</body>

</html>
<style>


    html,
    body {
        position: relative;
        height: 1240px;
        width: 1650px;
        margin: 0;
        padding: 0;
    }

    img {
        width: 1650px;
        height: 1240px;
        position: absolute;
        top: 0;
        left: 0;
        object-fit: cover;
        z-index: -1;
    }

    .container {
        position: absolute;
        width: 70%;
        left: 55%;
        top: 45%;
        transform: translate(-50%, -50%);
        font-family: Arial, Helvetica, sans-serif;
    }

    p {
        margin: 0;
        padding: 0;
    }

    .text-orange {
        color: #FF5733;
    }

    .font-small {
        font-size: 1.2em;
    }

    .font-xsmall {
        font-size: 2em;
    }

    .font-medium {
        font-size: 2.5em;
    }

    .font-large {
        font-size: 4em;
    }

    .font-xlarge {
        font-size: 5em;
    }

    .font-xxlarge {
        font-size: 6em;
    }

    .backgroud-gray {
        background-color: #b7b7b7;
    }

    .weight-medium {
        font-weight: 500;
    }

    .weight-semi-bold {
        font-weight: 600;
    }

    .weight-bold {
        font-weight: bold;
    }

    .weight-normal {
        font-weight: normal;
    }

    .weight-light {
        font-weight: lighter;
    }

    .font-italic {
        font-style: italic;
    }

    .family-lucida {
        font-family: 'lucide-handwriting', 'Lucida Handwriting', 'Lucida Sans Unicode', 'Lucida Grande', sans-serif;
    }

    .font-underline {
        text-decoration: underline;
    }

    .font-strike {
        text-decoration: line-through;
    }

    .text-uppercase {
        text-transform: uppercase;
    }

    .text-lowercase {
        text-transform: lowercase;
    }

    .text-capitalize {
        text-transform: capitalize;
    }

    .letter-spacing-small {
        letter-spacing: 0.05em;
    }