<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <div style="margin: auto; width: 100%;padding:12em 0;">
        <p class="text-orange font-medium weight-bold text-center" style="margin-bottom: 0.5em;">PRO BUSINESS</p>
        <div style="margin: 0 1em;"> 
            <p class="font-xlarge weight-bold letter-spacing-medium"> CONSTANCIA </p>
        <p class="font-medium weight-normal letter-spacing-small" style="margin: 0.3em 0;"> DE RECONOCIMIENTO  </p>
        <p class="font-small weight-medium" style="margin-bottom: 0.5em;">Otorgado a </p>
        <p class="family-lucida font-xsmall weight-medium" style="margin-left: 0.4em;margin-bottom: 0.6em;">
            <span class="backgroud-gray ">{{$nombre}}</span> </p>
        </div>
        <p class="font-small weight-light">Por haber participado exitosamente en el <span class="weight-medium">TALLER VIRTUAL DE IMPORTACIÓN,</span></p>
        <p class="font-small weight-light">equivalente a 16 horas académicas.</p>
        <p class="font-small weight-light" style="margin-top: 0.6em;">Dictado por el docente Miguel Villegas, en la modalidad Online. </p>
        <p class="font-small text-right" style="margin-top: 2.5em;">Lima, {{$fecha}} </p>
        
    </div>
</body>
</html>
<style>
    .whole {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        z-index: -1;
        transform: scale(1.4);
        opacity: 0.1;

    }
    body{
        position: relative;
        background-image: url('{{ asset("img/fondo.png") }}');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        height: 1200px;
        width: 1050px;
        margin: auto;
        font-family: Arial, Helvetica, sans-serif;
    }p {
        margin: 0;
        padding: 0;
    }.text-orange {
        color: #FF5733;
    }.font-small{
        font-size: 1.6em;
    }
    .font-xsmall{
        font-size: 3em;
    }
    .font-medium{
        font-size: 4em;
    }.font-large{
        font-size: 5em;
    }.font-xlarge{
        font-size: 6.5em;
    }.font-xxlarge{
        font-size: 6em;
    }.backgroud-gray{
        background-color: #b7b7b7;
    }.weight-medium{
        font-weight: 500;
    }.weight-semi-bold{
        font-weight: 600;
    }   .weight-bold{
        font-weight: bold;
    }.weight-normal{
        font-weight: normal;
    }.weight-light{
        font-weight: lighter;
    }.font-italic{
        font-style: italic;
    }.font-underline{
        text-decoration: underline;
    }.font-strike{
        text-decoration: line-through;
    }.text-uppercase{
        text-transform: uppercase;
    }.text-lowercase{
        text-transform: lowercase;
    }.text-capitalize{
        text-transform: capitalize;
    }.letter-spacing-small{
        letter-spacing: 0.05em;
    }.letter-spacing-medium{
        letter-spacing: 0.1em;
    }.letter-spacing-large{
        letter-spacing: 0.2em;
    }.text-justify{
        text-align: justify;
    }.text-center{
        text-align: center;
    }.text-right{
        text-align: right;
    }.text-left{
        text-align: left;
    }.family-lucida{
        font-family: 'Lucida Handwriting', 'Courier New', monospace;
    }.family-arial{
        font-family: Arial, Helvetica, sans-serif;
    }.family-georgia{
        font-family: Georgia, serif;
    }.family-times{
        font-family: 'Times New Roman', Times, serif;
    }.family-cursive{
        font-family: 'Brush Script MT', cursive;
    }
</style>