<?php
require_once __DIR__.'/_init.php';
?>
<!DOCTYPE html>
<html lang="es">

<head>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">

  <title>Agencia para el Desarrollo Económico de Misiones</title>

  <!-- Bootstrap core CSS -->
  <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

  <!-- Custom fonts for this template -->
  <link href="https://fonts.googleapis.com/css?family=Catamaran:100,200,300,400,500,600,700,800,900" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Lato:100,100i,300,300i,400,400i,700,700i,900,900i" rel="stylesheet">

  <!-- Custom styles for this template -->
  <link href="css/one-page-wonder.min.css" rel="stylesheet">
  <style>
    .btn-entrada {
      display: inline-block;
      min-width: 260px;
      padding: 1.15rem 2.6rem;
      font-weight: 700;
      letter-spacing: .08rem;
      text-transform: uppercase;
      border-radius: 5rem;
      text-decoration: none;
      box-shadow: 0 10px 24px rgba(0,0,0,.28);
    }
    .btn-entrada:hover,
    .btn-entrada:focus {
      text-decoration: none;
      transform: translateY(-1px);
    }
    .btn-entrada-registrar {
      background: #fff;
      color: #c2185b;
      border: 2px solid #fff;
    }
    .btn-entrada-registrar:hover,
    .btn-entrada-registrar:focus {
      background: #fff8fb;
      color: #9b1248;
    }
    .btn-entrada-login {
      background: #1a1a1a;
      color: #fff;
      border: 2px solid #1a1a1a;
    }
    .btn-entrada-login:hover,
    .btn-entrada-login:focus {
      background: #000;
      color: #fff;
      border-color: #000;
    }
    .masthead-heading {
      font-family: Catamaran, sans-serif;
      font-weight: 300;
      line-height: 1.2;
      margin: 0;
    }
    .titulo-agencia-linea {
      display: block;
      font-size: 1.05rem;
      font-weight: 400;
      letter-spacing: .32em;
      text-transform: uppercase;
      opacity: .92;
      margin-bottom: .85rem;
    }
    .titulo-agencia-nombre {
      display: block;
      font-size: 3.15rem;
      font-weight: 700;
      letter-spacing: .015em;
      text-shadow: 0 8px 28px rgba(0,0,0,.22);
    }
    @media (max-width: 767px) {
      .titulo-agencia-linea {
        font-size: .82rem;
        letter-spacing: .2em;
      }
      .titulo-agencia-nombre {
        font-size: 1.85rem;
      }
    }
  </style>

</head>

<body>

  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
    <div class="container">
      <a class="navbar-brand" href="#">ADEMI</a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarResponsive" aria-controls="navbarResponsive" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarResponsive">
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
            <a class="nav-link" href="admin/">Personal ADEMI</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <header class="masthead text-center text-white">
    <div class="masthead-content">
      <div class="container">
        <h1 class="masthead-heading mb-0">
          <span class="titulo-agencia-linea">Agencia para el</span>
          <span class="titulo-agencia-nombre">Desarrollo Económico<br>de Misiones</span>
        </h1>
        <div class="mt-5">
          <a href="registration.php" class="btn-entrada btn-entrada-registrar">Registrarse</a>
        </div>
        <div class="mt-3">
          <a href="login.php" class="btn-entrada btn-entrada-login">Iniciar sesión</a>
        </div>
      </div>
    </div>
    <div class="bg-circle-1 bg-circle"></div>
    <div class="bg-circle-2 bg-circle"></div>
    <div class="bg-circle-3 bg-circle"></div>
    <div class="bg-circle-4 bg-circle"></div>
  </header>


  <!-- Footer -->
  <footer class="py-5 bg-black">
    <div class="container">
      <p class="m-0 text-center text-white small">Agencia para el Desarrollo Económico de Misiones</p>
    </div>
    <!-- /.container -->
  </footer>

  <!-- Bootstrap core JavaScript -->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>

</html>
